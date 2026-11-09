<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Comprador;
use App\Models\Evento;
use App\Models\Ingresso;
use App\Support\Auditoria;
use App\Support\CancelamentoIngressos;
use App\Support\Notificacoes;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Vendas → Participantes: lista por evento, reenvio do ingresso, cancelamento e exportação. */
class ParticipantesController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('participante.ver');
        [$eventos, $evento] = self::eventoEscolhido($request);
        $f = $request->validate(['busca' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:'.implode(',', array_keys(Ingresso::STATUS))], 'lote' => ['nullable', 'integer']]);

        $ingressos = $evento ? $this->consulta($evento, $f)->paginate(50)->withQueryString() : collect();
        $resumo = $evento ? Ingresso::where('evento_id', $evento->id)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status') : collect();
        $lotes = $evento ? $evento->lotes()->orderBy('ordem')->pluck('nome', 'id') : collect();

        return view('painel.participantes.index', compact('eventos', 'evento', 'ingressos', 'resumo', 'lotes', 'f'));
    }

    public function exportar(Request $request)
    {
        Gate::authorize('participante.exportar');
        [, $evento] = self::eventoEscolhido($request);
        abort_unless($evento, 404);
        $completo = Gate::allows('participante.ver_dados_completos');
        $linhas = $this->consulta($evento, $request->only(['busca', 'status', 'lote']))->get();
        Auditoria::registrar('participante.exportar', $evento, null, ['quantidade' => $linhas->count(), 'dados_completos' => $completo]);

        $nome = 'participantes-'.$evento->slug.'-'.now()->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () use ($linhas, $completo) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: acentos corretos no Excel
            fputcsv($out, ['Ingresso', 'Participante', 'E-mail', 'CPF', 'Telefone', 'Tipo', 'Lote', 'Valor pago', 'Pedido', 'Comprador', 'Situação', 'Check-in'], ';');
            foreach ($linhas as $i) {
                $p = $i->participante;
                fputcsv($out, [
                    $i->numeroExibicao(), $p?->nome, $p?->email,
                    $p?->cpf ? ($completo ? $p->cpf : '***.'.substr($p->cpf, 3, 3).'.***-**') : '',
                    $completo ? $p?->telefone : '', $i->tipo?->nome, $i->lote?->nome, number_format((float) $i->valor_pago, 2, ',', '.'),
                    $i->pedido?->codigo, $i->pedido?->comprador?->nome, Ingresso::STATUS[$i->status] ?? $i->status,
                    $i->primeiro_checkin_em ? data_local($i->primeiro_checkin_em) : '',
                ], ';');
            }
            fclose($out);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Reenvia o e-mail do ingresso para o participante ou para o comprador. */
    /** Imprimir ingresso: o mesmo PDF do e-mail, só deste ingresso. */
    public function pdf(Ingresso $ingresso)
    {
        Gate::authorize('participante.ver');
        $this->autorizarEvento($ingresso);
        abort_if(! in_array($ingresso->status, ['valido', 'utilizado'], true), 422, 'Ingresso cancelado não tem PDF.');
        $ingresso->load(['participante', 'tipo', 'lote', 'pedido', 'evento.local', 'evento.organizador']);

        return response(\App\Support\IngressoPdf::gerar($ingresso->pedido, $ingresso->evento, collect([$ingresso])), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="ingresso-'.$ingresso->numeroExibicao().'.pdf"',
        ]);
    }

    public function reenviar(Request $request, Ingresso $ingresso)
    {
        Gate::authorize('participante.ver');
        $this->autorizarEvento($ingresso);
        $destino = $request->validate(['destino' => ['required', 'in:participante,comprador']])['destino'];
        $ingresso->load(['participante', 'tipo', 'lote', 'pedido.comprador', 'evento.local']);
        abort_if(! in_array($ingresso->status, ['valido', 'utilizado'], true), 422, 'Ingresso cancelado não pode ser reenviado.');

        $comprador = $ingresso->pedido->comprador;
        if ($destino === 'participante') {
            $email = $ingresso->participante?->email;
            if (! $email) {
                return back()->withErrors(['reenvio' => 'Este participante não tem e-mail cadastrado. Envie para o comprador.']);
            }
            $comprador = new Comprador(['nome' => $ingresso->participante->nome, 'email' => $email]);
            $comprador->id = $ingresso->pedido->comprador_id;
        }
        Notificacoes::ingressosConfirmados($ingresso->pedido, $comprador, $ingresso->evento, app(Tenant::class)->organizador(), collect([$ingresso]));
        Auditoria::registrar('ingresso.reenviar', $ingresso, null, ['destino' => $destino, 'email' => $comprador->email]);

        return back()->with('ok', "Ingresso {$ingresso->numeroExibicao()} reenviado para {$comprador->email}.");
    }

    public function cancelar(Request $request, Ingresso $ingresso)
    {
        Gate::authorize('pedido.reembolsar');
        $this->autorizarEvento($ingresso);
        $d = self::validarCancelamento($request);
        $ingresso->load(['participante', 'pedido.comprador', 'evento']);
        $r = CancelamentoIngressos::cancelar($ingresso->pedido, collect([$ingresso]), $d['motivo'], $d['observacao'] ?? null, (bool) ($d['devolvido'] ?? false));
        if (! $r['cancelados']) {
            return back()->withErrors(['cancelamento' => 'Só ingressos válidos (sem check-in) podem ser cancelados.']);
        }
        if ($request->boolean('avisar', true)) {
            Notificacoes::ingressosCanceladosPelaOrganizacao($ingresso->pedido, $ingresso->pedido->comprador, $ingresso->evento, app(Tenant::class)->organizador(), collect([$ingresso]), $r['valor']);
        }

        return back()->with('ok', "Ingresso {$ingresso->numeroExibicao()} cancelado.".($r['valor'] > 0 ? ' Valor a devolver ao comprador: '.moeda($r['valor']).'.' : ''));
    }

    // ---------- apoio ----------

    public static function validarCancelamento(Request $request): array
    {
        return $request->validate([
            'motivo' => ['required', 'in:'.implode(',', array_keys(CancelamentoIngressos::MOTIVOS))],
            'observacao' => ['nullable', 'string', 'max:500'], 'devolvido' => ['nullable', 'boolean'], 'avisar' => ['nullable', 'boolean'],
        ], [], ['motivo' => 'motivo do cancelamento']);
    }

    /** Eventos que o usuário pode ver e o escolhido (?evento=uuid; sem ele, o próximo ou o mais recente). */
    public static function eventoEscolhido(Request $request): array
    {
        $eventos = Evento::visiveis()->orderByDesc('data_inicio')->get(['id', 'uuid', 'titulo', 'slug', 'data_inicio', 'status']);
        $evento = $request->filled('evento') ? $eventos->firstWhere('uuid', $request->query('evento'))
            : ($eventos->where('data_inicio', '>=', now())->sortBy('data_inicio')->first() ?? $eventos->first());

        return [$eventos, $evento ? Evento::find($evento->id) : null];
    }

    private function consulta(Evento $evento, array $f)
    {
        return Ingresso::with(['participante', 'tipo', 'lote', 'pedido.comprador'])->where('evento_id', $evento->id)
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['lote'] ?? null, fn ($q, $l) => $q->where('lote_id', $l))
            ->when($f['busca'] ?? null, function ($q, $b) {
                $num = (int) preg_replace('/^.*-/', '', preg_replace('/[^0-9-]/', '', $b));
                $q->where(fn ($w) => $w->whereHas('participante', fn ($p) => $p->where('nome', 'like', "%{$b}%")->orWhere('email', 'like', "%{$b}%"))
                    ->orWhereHas('pedido', fn ($p) => $p->where('codigo', strtoupper($b)))
                    ->when($num > 0 && preg_match('/\d/', $b), fn ($x) => $x->orWhere('numero', $num)));
            })
            ->orderBy('numero');
    }

    private function autorizarEvento(Ingresso $ingresso): void
    {
        abort_unless(Evento::visiveis()->whereKey($ingresso->evento_id)->exists(), 404);
    }
}
