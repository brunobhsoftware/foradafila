<?php

namespace App\Support;

use App\Models\Ingresso;
use App\Models\Pedido;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cancelamento de ingressos pela organização (painel). Devolve o estoque do lote, marca os ingressos,
 * atualiza o pedido e, se houve valor pago, registra a devolução em `reembolso` (a devolução do dinheiro é
 * responsabilidade do produtor; com a integração de pagamento, sai pela API da operadora).
 */
class CancelamentoIngressos
{
    public const MOTIVOS = [
        'decisao_organizador' => 'Decisão da organização', 'arrependimento' => 'Arrependimento do comprador (até 7 dias)',
        'evento_cancelado' => 'Evento cancelado', 'evento_alterado' => 'Evento alterado (data/local)', 'outro' => 'Outro',
    ];

    /** @param Collection<int,Ingresso> $ingressos (do mesmo pedido) */
    public static function cancelar(Pedido $pedido, Collection $ingressos, string $motivo, ?string $observacao, bool $devolvido): array
    {
        $alvos = $ingressos->where('status', 'valido')->values();
        if ($alvos->isEmpty()) {
            return ['cancelados' => 0, 'valor' => 0.0];
        }
        $valor = round((float) $alvos->sum('valor_pago'), 2);

        DB::transaction(function () use ($pedido, $alvos, $motivo, $observacao, $devolvido, $valor) {
            foreach ($alvos->groupBy('lote_id')->sortKeys() as $loteId => $grupo) {
                DB::update("UPDATE lote SET vendido = vendido - ?, status = IF(status = 'esgotado', 'ativo', status) WHERE id = ? AND vendido >= ?",
                    [$grupo->count(), $loteId, $grupo->count()]);
            }
            $novoStatus = $valor > 0 ? 'reembolsado' : 'cancelado';
            Ingresso::withoutGlobalScope('organizador')->whereIn('id', $alvos->pluck('id'))->update(['status' => $novoStatus, 'cancelado_em' => now('UTC')]);

            $restantes = Ingresso::withoutGlobalScope('organizador')->where('pedido_id', $pedido->id)->whereIn('status', ['valido', 'utilizado'])->count();
            $antes = $pedido->getAttributes();
            if ($restantes === 0) {
                $teveDevolucao = $valor > 0 || (float) $pedido->valor_total > 0 || DB::table('reembolso')->where('pedido_id', $pedido->id)->exists();
                $pedido->forceFill(['status' => $teveDevolucao ? 'reembolsado' : 'cancelado', 'cancelado_em' => now('UTC')])->save();
            } elseif ($valor > 0) {
                $pedido->forceFill(['status' => 'reembolso_parcial'])->save();
            }

            if ($valor > 0) {
                $reembolsoId = DB::table('reembolso')->insertGetId([
                    'organizador_id' => $pedido->organizador_id, 'pedido_id' => $pedido->id, 'valor' => $valor, 'motivo' => $motivo,
                    'observacao' => $observacao ? mb_substr($observacao, 0, 500) : null, 'solicitado_por' => 'organizador',
                    'usuario_id' => auth()->id(), 'status' => $devolvido ? 'concluido' : 'aprovado', 'concluido_em' => $devolvido ? now('UTC') : null,
                ]);
                foreach ($alvos as $i) {
                    DB::table('reembolso_ingresso')->insert(['reembolso_id' => $reembolsoId, 'ingresso_id' => $i->id, 'valor' => $i->valor_pago]);
                }
            }
            Auditoria::registrar('ingresso.cancelar_pela_organizacao', $pedido, $antes, [
                'ingressos' => $alvos->map->numeroExibicao()->all(), 'valor_devolver' => $valor, 'motivo' => $motivo,
                'observacao' => $observacao, 'devolvido' => $devolvido,
            ]);
        });

        return ['cancelados' => $alvos->count(), 'valor' => $valor];
    }
}
