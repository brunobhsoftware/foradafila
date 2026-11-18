<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Ingresso;
use App\Models\Pedido;
use App\Support\Auditoria;
use App\Support\Notificacoes;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Área do comprador. Cada consulta filtra pelo comprador logado (guard comprador):
 * ninguém vê pedido ou ingresso de outra pessoa, mesmo trocando o endereço.
 */
class MeusIngressosController extends Controller
{
    public function index(Request $request)
    {
        $comprador = $request->user('comprador');
        $pedidos = $comprador->pedidos()
            ->with(['evento.local' => fn ($q) => $q->withoutGlobalScope('organizador'), 'organizadorPublico', 'ingressos.tipo', 'ingressos.lote', 'ingressos.participante'])
            ->orderByDesc('criado_em')->get();

        return view('comprador.ingressos', compact('pedidos', 'comprador'));
    }

    /** Segunda via: o MESMO ingresso e o mesmo QR Code; nada é gerado de novo. */
    public function show(Request $request, string $uuid)
    {
        $ingresso = $this->ingressoDoComprador($request, $uuid);
        $ingresso->load(['evento.local' => fn ($q) => $q->withoutGlobalScope('organizador'), 'tipo', 'lote', 'participante', 'pedido.organizadorPublico']);

        return view('comprador.ingresso', compact('ingresso'));
    }

    /**
     * Cancelar inscrição GRATUITA: o próprio titular desiste e a vaga volta ao lote.
     * Não envolve dinheiro. Pedidos pagos seguem outro caminho (pedido ao organizador),
     * que depende da integração de pagamento.
     */
    public function cancelar(Request $request, string $uuid, Tenant $tenant)
    {
        abort_unless(config('foradafila.comprador_cancela_gratuito'), 404);
        $pedido = $request->user('comprador')->pedidos()->with(['evento', 'ingressos'])->where('uuid', $uuid)->firstOrFail();

        if (! $this->podeCancelarGratuito($pedido)) {
            throw ValidationException::withMessages(['pedido' => 'Este pedido não pode mais ser cancelado por aqui. Fale com o organizador.']);
        }

        $tenant->definir($pedido->organizadorPublico);
        DB::transaction(function () use ($pedido) {
            $validos = $pedido->ingressos->where('status', 'valido');
            // O ingresso cancelado NÃO volta ao estoque (decisão do Bruno, 15/11/2026): lote.vendido não diminui, para não reabrir
            // lote esgotado nem mexer na fila dos lotes seguintes ("Por lote").
            Ingresso::withoutGlobalScope('organizador')->whereIn('id', $validos->pluck('id'))
                ->update(['status' => 'cancelado', 'cancelado_em' => now()]);
            $antes = $pedido->getAttributes();
            $pedido->forceFill(['status' => 'cancelado', 'cancelado_em' => now()])->save();
            Auditoria::registrar('pedido.gratuito.cancelar_pelo_comprador', $pedido, $antes);
        });

        Notificacoes::inscricaoCancelada($pedido, $request->user('comprador'), $pedido->evento, $pedido->organizadorPublico);

        return redirect()->route('comprador.ingressos')->with('ok', "Inscrição do pedido {$pedido->codigo} cancelada. Os ingressos deixaram de valer.");
    }

    public const DIAS_ARREPENDIMENTO = 7;

    /**
     * Regra do cancelamento pelo comprador (decisão do Bruno, 09/11/2026): até 7 dias da compra devolve tudo (ingresso + taxa);
     * depois, devolve só o valor do ingresso (a taxa de serviço repassada fica retida). Só antes do início do evento.
     *
     * @return array{pode: bool, motivo: ?string, valor: float, taxa_retida: float, dentro: bool, dias: int, compra: \Illuminate\Support\Carbon}
     */
    public static function regraCancelamento(Ingresso $ingresso, Pedido $pedido): array
    {
        $compra = \Illuminate\Support\Carbon::parse($pedido->pago_em ?? $pedido->criado_em);
        $dentro = $compra->copy()->addDays(self::DIAS_ARREPENDIMENTO)->isFuture();
        $taxa = 0.0;
        if ((float) $pedido->valor_taxa_servico > 0) { // taxa repassada ao comprador: está dentro do valor pago
            $taxa = (float) DB::table('pedido_item')->where('pedido_id', $pedido->id)->where('lote_id', $ingresso->lote_id)->value('taxa_unitaria');
        }
        $pago = (float) $ingresso->valor_pago;
        $retida = $dentro ? 0.0 : min($taxa, $pago);
        $motivo = match (true) {
            $ingresso->status !== 'valido' => 'Este ingresso não está mais válido.',
            ! in_array($pedido->status, ['pago', 'reembolso_parcial'], true) => 'Este pedido não pode ser cancelado por aqui.',
            ! $pedido->evento?->data_inicio?->isFuture() => 'O evento já começou: fale com o organizador.',
            $pago <= 0 && ! config('foradafila.comprador_cancela_gratuito') => 'Fale com o organizador para cancelar.',
            default => null,
        };

        return ['pode' => $motivo === null, 'motivo' => $motivo, 'valor' => round($pago - $retida, 2), 'taxa_retida' => round($retida, 2),
            'dentro' => $dentro, 'dias' => (int) floor($compra->diffInDays(now())), 'compra' => $compra];
    }

    /** O comprador cancela UM ingresso (gratuito ou pago); a devolução sai pelo Mercado Pago quando foi pago pelo checkout. */
    public function cancelarIngresso(Request $request, string $uuid, Tenant $tenant)
    {
        $ingresso = $this->ingressoDoComprador($request, $uuid);
        $pedido = Pedido::withoutGlobalScopes()->with(['evento', 'organizadorPublico'])->findOrFail($ingresso->pedido_id);
        $tenant->definir($pedido->organizadorPublico);
        $ingresso->load('participante');
        $regra = self::regraCancelamento($ingresso, $pedido);
        if (! $regra['pode']) {
            throw ValidationException::withMessages(['cancelar' => $regra['motivo']]);
        }
        \App\Support\CancelamentoIngressos::cancelar($pedido, collect([$ingresso]), 'arrependimento',
            'Cancelado pelo comprador em Meus ingressos ('.($regra['dentro'] ? 'até 7 dias: devolução integral' : 'após 7 dias: taxa retida').')', false,
            [$ingresso->id => $regra['valor']], 'comprador');
        Notificacoes::ingressoCanceladoPeloComprador($pedido, $request->user('comprador'), $pedido->evento, $pedido->organizadorPublico, $ingresso, $regra);

        return redirect()->route('comprador.ingressos')->with('ok', 'Ingresso '.$ingresso->numeroExibicao().' cancelado.'
            .($regra['valor'] > 0 ? ' Valor devolvido: '.moeda($regra['valor']).($regra['taxa_retida'] > 0 ? ' (sem a taxa de serviço).' : ' (integral).') : '')
            .' Enviamos a confirmação para o seu e-mail.');
    }

    /**
     * Transferência de titularidade (decisão do Bruno, 18/11/2026): o comprador passa UM ingresso para outra pessoa, se o evento permitir
     * e até X horas antes do início (evento.transferencia_ate_h). O QR Code antigo deixa de valer (código novo) e o novo titular recebe o
     * ingresso por e-mail. Cada ingresso pode ser transferido uma vez (ingresso_transferencia.uq_transf_origem).
     *
     * @return array{pode: bool, motivo: ?string, ate: ?\Illuminate\Support\Carbon}
     */
    public static function regraTransferencia(Ingresso $ingresso, Pedido $pedido): array
    {
        $evento = $pedido->evento;
        $ate = $evento?->data_inicio?->copy()->subHours((int) $evento->transferencia_ate_h);
        $motivo = match (true) {
            ! $evento || ! $evento->permite_transferencia => 'Este evento não permite transferência.',
            $ingresso->status !== 'valido' => 'Este ingresso não está mais válido.',
            ! in_array($pedido->status, ['pago', 'reembolso_parcial'], true) => 'Este pedido não pode ser transferido.',
            DB::table('ingresso_transferencia')->where('ingresso_origem_id', $ingresso->id)->exists() => 'Este ingresso já foi transferido uma vez.',
            ! $ate || $ate->isPast() => 'O prazo para transferir terminou.',
            default => null,
        };

        return ['pode' => $motivo === null, 'motivo' => $motivo, 'ate' => $ate];
    }

    public function transferirIngresso(Request $request, string $uuid, Tenant $tenant)
    {
        $ingresso = $this->ingressoDoComprador($request, $uuid);
        $pedido = Pedido::withoutGlobalScopes()->with(['evento', 'organizadorPublico'])->findOrFail($ingresso->pedido_id);
        $tenant->definir($pedido->organizadorPublico);
        $ingresso->load(['participante', 'tipo', 'lote']);
        $regra = self::regraTransferencia($ingresso, $pedido);
        if (! $regra['pode']) {
            throw ValidationException::withMessages(['transferir' => $regra['motivo']]);
        }
        $d = $request->validate([
            'para_nome' => ['required', 'string', 'max:150', new \App\Rules\NomeCompleto],
            'para_email' => ['required', 'email:filter', 'max:150'],
            'confirmo' => ['accepted'],
        ], ['confirmo.accepted' => 'Confirme que o ingresso deixará de valer para você.'], ['para_nome' => 'nome do novo titular', 'para_email' => 'e-mail do novo titular']);
        $deNome = $ingresso->participante?->nome ?? $request->user('comprador')->nome;
        $email = mb_strtolower($d['para_email']);

        DB::transaction(function () use ($ingresso, $d, $email, $deNome, $request) {
            $ingresso->forceFill(['codigo_qr' => Ingresso::novoCodigoQr()])->save(); // o QR antigo deixa de valer
            if ($ingresso->participante) {
                $ingresso->participante->forceFill(['nome' => $d['para_nome'], 'email' => $email, 'cpf' => null, 'telefone' => null, 'mesmo_que_comprador' => 0])->save();
            }
            DB::table('ingresso_transferencia')->insert([
                'ingresso_origem_id' => $ingresso->id, 'ingresso_destino_id' => $ingresso->id, 'de_nome' => mb_substr($deNome, 0, 150),
                'para_nome' => \App\Support\NomePessoa::padronizar($d['para_nome']), 'para_email' => $email, 'solicitado_por' => 'comprador', 'ip' => $request->ip(),
            ]);
        });
        Auditoria::registrar('ingresso.transferir', $ingresso, null, ['de' => $deNome, 'para' => $d['para_nome'], 'para_email' => $email]);
        $evento = \App\Models\Evento::withoutGlobalScopes()->with(['organizador', 'local'])->findOrFail($pedido->evento_id);
        Notificacoes::ingressoTransferido($pedido, $evento, $evento->organizador, $ingresso->fresh(['participante', 'tipo', 'lote']), $deNome);

        return redirect()->route('comprador.ingressos')->with('ok', 'Ingresso '.$ingresso->numeroExibicao().' transferido para '.$d['para_nome']
            .'. Enviamos o ingresso para '.$email.'; o QR Code anterior não vale mais.');
    }

    /** "Enviar para meu e-mail" de um ingresso. */
    public function enviarIngresso(Request $request, string $uuid, Tenant $tenant)
    {
        $ingresso = $this->ingressoDoComprador($request, $uuid);
        abort_unless(in_array($ingresso->status, ['valido', 'utilizado'], true), 422);
        $pedido = Pedido::withoutGlobalScopes()->findOrFail($ingresso->pedido_id);
        $evento = \App\Models\Evento::withoutGlobalScopes()->with('organizador')->findOrFail($pedido->evento_id);
        $tenant->definir($evento->organizador);
        $evento->load('local');
        $ingresso->load(['participante', 'tipo', 'lote']);
        Notificacoes::ingressosConfirmados($pedido, $request->user('comprador'), $evento, $evento->organizador, collect([$ingresso]));

        return back()->with('ok', 'Enviamos o ingresso '.$ingresso->numeroExibicao().' para o seu e-mail, em PDF.');
    }

    public static function podeCancelarGratuito(Pedido $pedido): bool
    {
        return config('foradafila.comprador_cancela_gratuito')
            && $pedido->status === 'pago' && $pedido->ehGratuito()
            && $pedido->evento->data_inicio->isFuture()
            && $pedido->ingressos->where('status', 'utilizado')->isEmpty()
            && $pedido->ingressos->where('status', 'valido')->isNotEmpty();
    }

    private function ingressoDoComprador(Request $request, string $uuid): Ingresso
    {
        return Ingresso::withoutGlobalScope('organizador')->where('uuid', $uuid)
            ->whereHas('pedido', fn ($q) => $q->where('comprador_id', $request->user('comprador')->id))
            ->firstOrFail();
    }
}
