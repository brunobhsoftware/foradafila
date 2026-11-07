<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Pedido;
use App\Support\Auditoria;
use App\Support\CancelamentoIngressos;
use App\Support\Notificacoes;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Vendas → Pedidos: lista com filtros, detalhe, reenvio da confirmação e cancelamento do pedido. */
class PedidosController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('pedido.ver');
        $eventos = Evento::visiveis()->orderByDesc('data_inicio')->get(['id', 'uuid', 'titulo']);
        $f = $request->validate(['evento' => ['nullable', 'string'], 'status' => ['nullable', 'in:'.implode(',', array_keys(Pedido::STATUS))], 'busca' => ['nullable', 'string', 'max:100']]);
        $evento = ($f['evento'] ?? null) ? $eventos->firstWhere('uuid', $f['evento']) : null;

        $pedidos = Pedido::with(['comprador', 'evento'])->withCount('ingressos')
            ->whereIn('evento_id', $evento ? [$evento->id] : $eventos->pluck('id'))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['busca'] ?? null, fn ($q, $b) => $q->where(fn ($w) => $w->where('codigo', strtoupper($b))
                ->orWhereHas('comprador', fn ($c) => $c->where('nome', 'like', "%{$b}%")->orWhere('email', 'like', "%{$b}%"))))
            ->orderByDesc('id')->paginate(30)->withQueryString();
        $totais = Pedido::whereIn('evento_id', $evento ? [$evento->id] : $eventos->pluck('id'))->whereIn('status', ['pago', 'reembolso_parcial'])
            ->selectRaw('COUNT(*) n, COALESCE(SUM(valor_total),0) total')->first();

        return view('painel.pedidos.index', compact('pedidos', 'eventos', 'evento', 'f', 'totais'));
    }

    public function show(Pedido $pedido)
    {
        Gate::authorize('pedido.ver');
        $this->autorizarEvento($pedido);
        $pedido->load(['comprador', 'evento', 'ingressos.participante', 'ingressos.tipo', 'ingressos.lote']);
        $reembolsos = DB::table('reembolso')->where('pedido_id', $pedido->id)->orderByDesc('id')->get();

        return view('painel.pedidos.show', compact('pedido', 'reembolsos'));
    }

    public function reenviar(Pedido $pedido)
    {
        Gate::authorize('pedido.ver');
        $this->autorizarEvento($pedido);
        $pedido->load(['comprador', 'evento.local', 'ingressos.participante', 'ingressos.tipo', 'ingressos.lote']);
        $validos = $pedido->ingressos->whereIn('status', ['valido', 'utilizado']);
        abort_if($validos->isEmpty(), 422, 'Pedido sem ingressos válidos.');
        Notificacoes::ingressosConfirmados($pedido, $pedido->comprador, $pedido->evento, app(Tenant::class)->organizador(), $validos);
        Auditoria::registrar('pedido.reenviar_confirmacao', $pedido);

        return back()->with('ok', "Confirmação do pedido {$pedido->codigo} reenviada para {$pedido->comprador->email}.");
    }

    public function cancelar(Request $request, Pedido $pedido)
    {
        Gate::authorize('pedido.reembolsar');
        $this->autorizarEvento($pedido);
        $d = ParticipantesController::validarCancelamento($request);
        $pedido->load(['comprador', 'evento', 'ingressos.participante']);
        $r = CancelamentoIngressos::cancelar($pedido, $pedido->ingressos, $d['motivo'], $d['observacao'] ?? null, (bool) ($d['devolvido'] ?? false));
        if (! $r['cancelados']) {
            return back()->withErrors(['cancelamento' => 'Este pedido não tem ingressos válidos para cancelar (os já usados no check-in não são cancelados).']);
        }
        if ($request->boolean('avisar', true)) {
            Notificacoes::ingressosCanceladosPelaOrganizacao($pedido, $pedido->comprador, $pedido->evento, app(Tenant::class)->organizador(),
                $pedido->ingressos->whereIn('status', ['valido'])->values()->whenEmpty(fn () => $pedido->ingressos), $r['valor']);
        }

        return back()->with('ok', "{$r['cancelados']} ingresso(s) do pedido {$pedido->codigo} cancelado(s).".($r['valor'] > 0 ? ' Valor a devolver: '.moeda($r['valor']).'.' : ''));
    }

    private function autorizarEvento(Pedido $pedido): void
    {
        abort_unless(Evento::visiveis()->whereKey($pedido->evento_id)->exists(), 404);
    }
}
