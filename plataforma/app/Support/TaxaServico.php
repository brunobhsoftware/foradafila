<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\Organizador;

/**
 * Quem paga a taxa da plataforma num ingresso pago (V010).
 * - Embutida: o comprador paga o preço; a taxa sai do valor do produtor.
 * - Repassada: o comprador paga preço + taxa; o produtor recebe o preço cheio.
 * O valor da taxa vem do motor da Administração (MotorTaxas: percentual + fixo, com mínimo). Ingresso gratuito não tem taxa.
 */
class TaxaServico
{
    public static function repassada(Evento $evento, ?Organizador $org = null): bool
    {
        if (! Esquema::temV010()) {
            return false;
        }
        if ($evento->taxa_comprador !== null) {
            return (bool) $evento->taxa_comprador;
        }
        $org ??= $evento->organizador()->withoutGlobalScopes()->first();

        return (bool) ($org?->taxa_comprador);
    }

    /** @return array{preco: float, taxa: float, comprador_paga: float, produtor_recebe: float, repassada: bool} */
    public static function paraPreco(Evento $evento, float $preco, ?Organizador $org = null): array
    {
        $org ??= $evento->organizador()->withoutGlobalScopes()->first();
        $repassada = self::repassada($evento, $org);
        $taxa = 0.0;
        if ($preco > 0 && $org && Esquema::temV008()) {
            $taxa = (float) MotorTaxas::calcular($org, 'ingresso_pago', $preco)['taxa'];
        }

        return [
            'preco' => round($preco, 2), 'taxa' => $taxa, 'repassada' => $repassada,
            'comprador_paga' => round($repassada ? $preco + $taxa : $preco, 2),
            'produtor_recebe' => round($repassada ? $preco : max(0, $preco - $taxa), 2),
        ];
    }
}
