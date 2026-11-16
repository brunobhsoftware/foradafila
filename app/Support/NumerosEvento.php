<?php

namespace App\Support;

use App\Http\Controllers\Publico\EventoPublicoController;
use App\Models\Evento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Números de vendas de um evento — fonte ÚNICA para o app (/app), Meus eventos, a página do evento e o Dashboard
 * (decisão do Bruno, 16/11/2026: em todo o ecossistema os valores e os nomes devem ser iguais).
 *
 * - Ingressos vendidos: ingressos válidos/utilizados (cancelados e devolvidos saem da conta); "de N ofertados" = Σ lote.quantidade.
 * - Total vendido: Σ pedido.valor_total (pago, reembolso parcial, reembolsado) − devolvido.
 * - Descontos (cupons): Σ pedido.valor_desconto dos mesmos pedidos.
 * - Taxa da plataforma: Σ pedido_item.taxa_unitaria dos ingressos válidos.
 * - Devolvido: Σ reembolso aprovado/concluído.
 * - Líquido a receber: total pago − taxa − devolvido.
 * - Pedidos: pedidos com pelo menos um ingresso válido.
 */
class NumerosEvento
{
    public const ROTULOS = [
        'ingressos' => 'Ingressos vendidos',
        'total' => 'Total vendido',
        'descontos' => 'Descontos (cupons)',
        'taxa' => 'Taxa da plataforma',
        'devolvido' => 'Devolvido',
        'liquido' => 'Líquido a receber',
        'pedidos' => 'Pedidos',
        'checkins' => 'Check-ins',
    ];

    private const PAGOS = ['pago', 'reembolso_parcial', 'reembolsado'];
    private const VALIDOS = ['valido', 'utilizado'];

    /** Resumo de vários eventos de uma vez (Meus eventos, Dashboard): [evento_id => objeto]. */
    public static function resumos(iterable $ids): Collection
    {
        $ids = collect($ids)->values();
        if ($ids->isEmpty()) {
            return collect();
        }
        $pedidos = DB::table('pedido')->whereIn('evento_id', $ids)->whereIn('status', self::PAGOS)->groupBy('evento_id')
            ->selectRaw('evento_id, SUM(valor_total) AS bruto, SUM(valor_desconto) AS descontos, SUM(cupom_id IS NOT NULL AND valor_desconto > 0) AS cupons')->get()->keyBy('evento_id');
        $ingressos = DB::table('ingresso as i')->leftJoin('pedido_item as pi', 'pi.id', '=', 'i.pedido_item_id')
            ->whereIn('i.evento_id', $ids)->whereIn('i.status', self::VALIDOS)->groupBy('i.evento_id')
            ->selectRaw('i.evento_id, COUNT(*) AS qtd, COALESCE(SUM(pi.taxa_unitaria), 0) AS taxa, COUNT(DISTINCT i.pedido_id) AS pedidos,
                SUM(i.valor_pago = 0) AS cortesias, SUM(i.primeiro_checkin_em IS NOT NULL) AS checkins')->get()->keyBy('evento_id');
        $cancelados = DB::table('ingresso')->whereIn('evento_id', $ids)->whereIn('status', ['cancelado', 'reembolsado'])
            ->groupBy('evento_id')->selectRaw('evento_id, COUNT(*) AS n')->pluck('n', 'evento_id');
        $devolvido = DB::table('reembolso as r')->join('pedido as p', 'p.id', '=', 'r.pedido_id')->whereIn('p.evento_id', $ids)
            ->whereIn('r.status', ['aprovado', 'concluido'])->groupBy('p.evento_id')->selectRaw('p.evento_id, SUM(r.valor) AS v')->pluck('v', 'p.evento_id');
        $ofertados = DB::table('lote')->whereIn('evento_id', $ids)->groupBy('evento_id')
            ->selectRaw('evento_id, SUM(quantidade) AS q, SUM(reservado) AS r')->get()->keyBy('evento_id');

        return $ids->mapWithKeys(function ($id) use ($pedidos, $ingressos, $cancelados, $devolvido, $ofertados) {
            $bruto = (float) ($pedidos[$id]->bruto ?? 0);
            $taxa = (float) ($ingressos[$id]->taxa ?? 0);
            $dev = (float) ($devolvido[$id] ?? 0);
            $qtdPedidos = (int) ($ingressos[$id]->pedidos ?? 0);
            $total = round($bruto - $dev, 2);

            return [$id => (object) [
                'ingressos' => (int) ($ingressos[$id]->qtd ?? 0),
                'ofertados' => (int) ($ofertados[$id]->q ?? 0),
                'reservados' => (int) ($ofertados[$id]->r ?? 0),
                'cortesias' => (int) ($ingressos[$id]->cortesias ?? 0),
                'checkins' => (int) ($ingressos[$id]->checkins ?? 0),
                'cancelados' => (int) ($cancelados[$id] ?? 0),
                'pedidos' => $qtdPedidos,
                'total' => $total,
                'descontos' => (float) ($pedidos[$id]->descontos ?? 0),
                'cupons' => (int) ($pedidos[$id]->cupons ?? 0),
                'taxa' => $taxa,
                'devolvido' => $dev,
                'liquido' => round($bruto - $taxa - $dev, 2),
                'ticket' => $qtdPedidos ? $total / $qtdPedidos : 0,
            ]];
        });
    }

    /** Soma dos resumos (Dashboard). */
    public static function somar(Collection $resumos): object
    {
        $s = (object) [];
        foreach (['ingressos', 'ofertados', 'reservados', 'cortesias', 'checkins', 'cancelados', 'pedidos', 'total', 'descontos', 'cupons', 'taxa', 'devolvido', 'liquido'] as $k) {
            $s->$k = $resumos->sum($k);
        }

        return $s;
    }

    /** Resumo + detalhes de um evento (app e página do evento). */
    public static function detalhado(Evento $evento): object
    {
        $r = self::resumos([$evento->id])[$evento->id];
        $inicioHoje = now()->setTimezone(JanelaCheckin::FUSO)->startOfDay()->utc();
        $validos = DB::table('ingresso as i')->where('i.evento_id', $evento->id)->whereIn('i.status', self::VALIDOS);
        $r->por_lote = (clone $validos)->join('lote as l', 'l.id', '=', 'i.lote_id')->join('ingresso_tipo as t', 't.id', '=', 'l.ingresso_tipo_id')
            ->groupBy('l.id', 'l.nome', 't.nome')->orderByDesc(DB::raw('COUNT(*)'))
            ->get(['l.id as lote_id', 't.nome as tipo', 'l.nome as lote', DB::raw('COUNT(*) as qtd'), DB::raw('SUM(i.valor_pago) as valor')]);
        $r->cancelados_por_lote = DB::table('ingresso')->where('evento_id', $evento->id)
            ->whereIn('status', ['cancelado', 'reembolsado'])->groupBy('lote_id')->pluck(DB::raw('COUNT(*)'), 'lote_id');
        $pagosHoje = DB::table('pedido')->where('evento_id', $evento->id)->whereIn('status', self::PAGOS)->where('pago_em', '>=', $inicioHoje);
        $hoje = (float) (clone $pagosHoje)->sum('valor_total') - (float) DB::table('reembolso')->whereIn('pedido_id', (clone $pagosHoje)->select('id'))
            ->whereIn('status', ['aprovado', 'concluido'])->sum('valor');
        $r->hoje = round(max($hoje, 0), 2);
        $r->ingressos_hoje = (int) (clone $validos)->where('i.criado_em', '>=', $inicioHoje)->count();
        $r->lotes = $evento->lotes()->with('tipo')->orderBy('id')->get()->map(fn ($l) => (object) [
            'nome' => ($l->tipo?->nome ? $l->tipo->nome.' — ' : '').$l->nome, 'preco' => (float) $l->preco,
            'vendidos' => (int) $l->vendido, 'quantidade' => (int) $l->quantidade,
            'cancelados' => (int) ($r->cancelados_por_lote[$l->id] ?? 0),
            'uuid' => $l->uuid,
            'situacao' => $l->vendido >= $l->quantidade ? 'Esgotado'
                : (EventoPublicoController::loteAVenda($evento, $l) ? 'À venda' : ($l->status === 'pausado' ? 'Desativado' : ($l->venda_fim && $l->venda_fim < now() ? 'Encerrado' : 'Ainda não abriu'))),
        ]);

        return $r;
    }
}
