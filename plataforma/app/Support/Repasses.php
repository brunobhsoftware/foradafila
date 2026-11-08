<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Saldo e repasses do recebimento pela plataforma (V012): pagamentos aprovados na conta da BH Software
 * (conta_pagamento.modo = plataforma) que ainda não entraram em um repasse.
 */
class Repasses
{
    /** Pagamentos aprovados ainda não repassados, com o evento e se já pode repassar (fim do evento + prazo). */
    public static function pendentes(?int $organizadorId = null)
    {
        return DB::table('pagamento as pg')
            ->join('conta_pagamento as c', 'c.id', '=', 'pg.conta_pagamento_id')
            ->join('pedido as p', 'p.id', '=', 'pg.pedido_id')
            ->join('evento as e', 'e.id', '=', 'p.evento_id')
            ->where('c.modo', 'plataforma')->where('pg.status', 'aprovado')->whereNull('pg.repasse_id')
            ->when($organizadorId, fn ($q) => $q->where('pg.organizador_id', $organizadorId))
            ->select('pg.id', 'pg.organizador_id', 'pg.valor', 'pg.comissao_retida', 'pg.valor_liquido_org', 'pg.aprovado_em',
                'p.codigo', 'p.status as pedido_status', 'e.titulo', 'e.data_fim')
            ->orderBy('e.data_fim')->get()
            ->map(function ($r) {
                $r->liberado_em = self::liberacao($r->data_fim);
                $r->pode_repassar = $r->liberado_em <= now()->toDateString();
                // pedido reembolsado/cancelado depois de pago: não entra no repasse (o valor volta ao comprador)
                $r->devolver = in_array($r->pedido_status, ['reembolsado', 'cancelado'], true);

                return $r;
            });
    }

    /** Data em que o repasse fica liberado: fim do evento + PRAZO_DIAS_UTEIS dias úteis. */
    public static function liberacao($dataFim): string
    {
        $d = \Illuminate\Support\Carbon::parse($dataFim, 'UTC')->setTimezone('America/Sao_Paulo')->startOfDay();
        for ($n = 0; $n < Recebimento::PRAZO_DIAS_UTEIS;) {
            $d->addDay();
            if (! $d->isWeekend()) {
                $n++;
            }
        }

        return $d->toDateString();
    }

    /** Resumo por organização: a repassar agora, aguardando o fim do evento, comissão. */
    public static function resumo(?int $organizadorId = null): array
    {
        $p = self::pendentes($organizadorId)->reject(fn ($r) => $r->devolver);

        return [
            'liberado' => round($p->where('pode_repassar', true)->sum('valor_liquido_org'), 2),
            'aguardando' => round($p->where('pode_repassar', false)->sum('valor_liquido_org'), 2),
            'comissao' => round($p->sum('comissao_retida'), 2),
            'vendas' => round($p->sum('valor'), 2),
        ];
    }
}
