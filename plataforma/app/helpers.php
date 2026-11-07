<?php

use Illuminate\Support\Carbon;

/*
| Funções de apoio usadas nas telas.
| Regra do projeto: o banco grava em UTC; a tela mostra e recebe no fuso de exibição.
*/

if (! function_exists('fuso_exibicao')) {
    function fuso_exibicao(): string
    {
        return config('app.display_timezone', 'America/Sao_Paulo');
    }
}

if (! function_exists('data_local')) {
    /** Data do banco (UTC) formatada no fuso de exibição. */
    function data_local($valor, string $formato = 'd/m/Y H:i'): string
    {
        if (blank($valor)) {
            return '—';
        }

        return Carbon::parse($valor, 'UTC')->setTimezone(fuso_exibicao())->format($formato);
    }
}

if (! function_exists('para_input_data')) {
    /** Valor para <input type="datetime-local"> no fuso de exibição. */
    function para_input_data($valor): string
    {
        return blank($valor) ? '' : Carbon::parse($valor, 'UTC')->setTimezone(fuso_exibicao())->format('Y-m-d\TH:i');
    }
}

if (! function_exists('utc_de_local')) {
    /** Converte o que o usuário digitou (fuso de exibição) para UTC, no formato do banco. */
    function utc_de_local(?string $valor): ?string
    {
        return blank($valor) ? null : Carbon::parse($valor, fuso_exibicao())->setTimezone('UTC')->format('Y-m-d H:i:s');
    }
}

if (! function_exists('moeda')) {
    function moeda($valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }
}

if (! function_exists('numero')) {
    function numero($valor, int $casas = 0): string
    {
        return number_format((float) $valor, $casas, ',', '.');
    }
}
