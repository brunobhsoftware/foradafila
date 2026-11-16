<?php

namespace App\Support;

use App\Pagamentos\MercadoPago;

/**
 * Confere se a taxa da plataforma cobre a tarifa do Mercado Pago que a BH absorve na venda da conta conectada
 * (a empresa recebe o valor cheio). A conta usa o pior caso: taxa repassada ao comprador, em que a tarifa
 * incide sobre o ingresso + a taxa.
 */
class MargemTaxas
{
    public const VALORES = [10, 20, 50, 100, 200, 500];

    /** Linhas por valor de ingresso: taxa, tarifa e sobra no Pix e no cartão; e o resumo. */
    public static function analisar(float $percentual, float $fixo, float $minimo): array
    {
        $pct = ['pix' => MercadoPago::tarifaPct('pix'), 'cartao' => MercadoPago::tarifaPct('cartao')];
        $linhas = [];
        foreach (self::VALORES as $v) {
            $taxa = round(max($v * $percentual / 100 + $fixo, $minimo), 2);
            $linha = ['valor' => $v, 'taxa' => $taxa];
            foreach ($pct as $forma => $t) {
                $tarifa = round(($v + $taxa) * $t / 100, 2);
                $linha[$forma] = ['tarifa' => $tarifa, 'sobra' => round($taxa - $tarifa, 2)];
            }
            $linhas[] = $linha;
        }
        $semSobra = fn (string $forma) => collect($linhas)->filter(fn ($l) => $l['taxa'] > 0 && $l[$forma]['sobra'] <= 0)->pluck('valor')->all();

        return [
            'configurado' => $pct['pix'] > 0 || $pct['cartao'] > 0,
            // Taxa zero em todos os valores: não há comissão a reter, então nada é abatido.
            'sem_taxa' => collect($linhas)->every(fn ($l) => $l['taxa'] <= 0),
            'pct' => $pct,
            'linhas' => $linhas,
            'sem_sobra' => ['pix' => $pct['pix'] > 0 ? $semSobra('pix') : [], 'cartao' => $pct['cartao'] > 0 ? $semSobra('cartao') : []],
            // Percentual a partir do qual a taxa cobre a tarifa em qualquer valor (sem contar fixo e mínimo): t / (1 − t).
            'minimo_pct' => array_map(fn ($t) => $t > 0 && $t < 100 ? round($t / (100 - $t) * 100, 2) : 0.0, $pct),
        ];
    }

    /** Aviso em uma frase para mostrar depois de salvar; null quando a taxa cobre a tarifa em todos os valores. */
    public static function aviso(float $percentual, float $fixo, float $minimo): ?string
    {
        $a = self::analisar($percentual, $fixo, $minimo);
        $partes = [];
        foreach (['pix' => 'Pix', 'cartao' => 'cartão'] as $forma => $nome) {
            if ($a['sem_sobra'][$forma]) {
                $partes[] = "no {$nome} (tarifa de ".numero($a['pct'][$forma], 2).'%) não sobra nada para a plataforma em ingressos de '
                    .implode(', ', array_map(fn ($v) => 'R$ '.$v, $a['sem_sobra'][$forma])).'; para cobrir, a taxa precisa passar de '.numero($a['minimo_pct'][$forma], 2).'%';
            }
        }

        return $partes ? 'Atenção: esta taxa não cobre a tarifa do Mercado Pago — '.implode('; ', $partes).'.' : null;
    }
}
