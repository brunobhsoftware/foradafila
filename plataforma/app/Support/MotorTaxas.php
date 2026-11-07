<?php

namespace App\Support;

use App\Models\Organizador;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Motor de taxas da plataforma (V008).
 * 1º taxa específica da empresa vigente na data (isenção ou valores próprios; campo vazio = usa o padrão);
 * 2º regra padrão ativa da operação, na faixa do volume movimentado no mês;
 * carência: contas mais novas que carencia_dias não pagam.
 * Taxa final = max(valor × % + fixo, mínimo).
 */
class MotorTaxas
{
    public const OPERACOES = [
        'ingresso_pago' => 'Ingresso pago', 'ingresso_gratuito' => 'Ingresso gratuito / cortesia',
        'pix' => 'Pagamento por Pix', 'cartao' => 'Pagamento por cartão', 'boleto' => 'Pagamento por boleto',
    ];

    /** Taxa aplicável. Retorna ['taxa' => float, 'origem' => texto, 'percentual','valor_fixo','valor_minimo','isento']. */
    public static function calcular(Organizador $org, string $operacao, float $valor, float $volumeMes = 0, ?Carbon $data = null): array
    {
        $data ??= now('UTC');
        $dia = $data->toDateString();

        $regra = DB::table('taxa_regra')->where('operacao', $operacao)->where('ativo', 1)
            ->where('volume_min', '<=', $volumeMes)
            ->where(fn ($q) => $q->whereNull('volume_max')->orWhere('volume_max', '>=', $volumeMes))
            ->orderByDesc('volume_min')->first();

        $especifica = DB::table('taxa_organizador')->where('organizador_id', $org->id)
            ->whereIn('operacao', [$operacao, 'todas'])->where('inicio', '<=', $dia)
            ->where(fn ($q) => $q->whereNull('fim')->orWhere('fim', '>=', $dia))
            ->orderByRaw("operacao = 'todas'")->orderByDesc('inicio')->first();

        if ($especifica && $especifica->isento) {
            return self::resultado(0, 0, 0, 'Isenção da empresa'.($especifica->fim ? ' até '.Carbon::parse($especifica->fim)->format('d/m/Y') : ''), $valor, true);
        }
        if ($regra && $regra->carencia_dias > 0 && $org->criado_em && Carbon::parse($org->criado_em)->addDays($regra->carencia_dias)->gte($data)) {
            return self::resultado(0, 0, 0, "Carência de {$regra->carencia_dias} dias para conta nova", $valor, true);
        }

        $p = (float) ($especifica->percentual ?? $regra->percentual ?? 0);
        $f = (float) ($especifica->valor_fixo ?? $regra->valor_fixo ?? 0);
        $m = (float) ($especifica->valor_minimo ?? $regra->valor_minimo ?? 0);
        $origem = $especifica ? 'Taxa específica da empresa' : ($regra ? 'Regra padrão: '.$regra->nome : 'Sem regra cadastrada');

        return self::resultado($p, $f, $m, $origem, $valor, false);
    }

    private static function resultado(float $p, float $f, float $m, string $origem, float $valor, bool $isento): array
    {
        $taxa = $isento ? 0.0 : round(max($valor * $p / 100 + $f, $m), 2);

        return ['taxa' => $taxa, 'origem' => $origem, 'percentual' => $p, 'valor_fixo' => $f, 'valor_minimo' => $m, 'isento' => $isento];
    }
}
