<?php

namespace App\Support;

use App\Models\Evento;
use Illuminate\Support\Facades\DB;

/**
 * "Vendas encerradas" manual (decisão do Bruno, 18/11/2026): no dia do evento o produtor encerra (ou reabre) as vendas na hora,
 * pelo painel ou pelo app, independente do período dos lotes. Com as vendas encerradas, nenhum lote fica à venda e a página
 * mostra "Vendas encerradas" (não "esgotado"). Guardado em parametro_sistema (vendas_encerradas_evento_<id> = data/hora UTC).
 */
class VendasEncerradas
{
    private static array $cache = [];

    private static function chave(int $eventoId): string
    {
        return 'vendas_encerradas_evento_'.$eventoId;
    }

    public static function ativo(Evento $evento): bool
    {
        return self::$cache[$evento->id] ??= DB::table('parametro_sistema')->where('chave', self::chave($evento->id))->exists();
    }

    /** Só no dia do evento (do dia do início ao dia do término, horário de Brasília) e com o evento à venda no site. */
    public static function podeAlterar(Evento $evento): bool
    {
        if (! in_array($evento->status, ['publicado', 'pausado'], true) || ! $evento->data_inicio) {
            return false;
        }
        $hoje = now(JanelaCheckin::FUSO)->toDateString();
        $inicio = $evento->data_inicio->copy()->setTimezone(JanelaCheckin::FUSO)->toDateString();
        $fim = ($evento->data_fim ?? $evento->data_inicio)->copy()->setTimezone(JanelaCheckin::FUSO)->toDateString();

        return $hoje >= $inicio && $hoje <= $fim;
    }

    public static function definir(Evento $evento, bool $encerrar): void
    {
        $encerrar
            ? DB::table('parametro_sistema')->updateOrInsert(['chave' => self::chave($evento->id)],
                ['valor' => now('UTC')->format('Y-m-d H:i:s'), 'descricao' => 'Vendas encerradas manualmente no dia do evento'])
            : DB::table('parametro_sistema')->where('chave', self::chave($evento->id))->delete();
        self::$cache[$evento->id] = $encerrar;
    }
}
