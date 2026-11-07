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
