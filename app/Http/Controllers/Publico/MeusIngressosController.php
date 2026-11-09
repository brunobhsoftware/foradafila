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
            foreach ($validos->groupBy('lote_id')->sortKeys() as $loteId => $ingressos) {
                DB::update("UPDATE lote SET vendido = vendido - ?, status = IF(status = 'esgotado', 'ativo', status) WHERE id = ? AND vendido >= ?",
                    [$ingressos->count(), $loteId, $ingressos->count()]);
            }
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
