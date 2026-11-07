<?php

namespace App\Support;

/** CPF e CNPJ: limpeza, validação dos dígitos verificadores e formatação. */
class Documento
{
    public static function digitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    public static function cpfValido(string $cpf): bool
    {
        $cpf = self::digitos($cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digito = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    public static function cnpjValido(string $cnpj): bool
    {
        $cnpj = self::digitos($cnpj);
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        $pesos = [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]];
        foreach ([12, 13] as $k => $pos) {
            $soma = 0;
            for ($i = 0; $i < $pos; $i++) {
                $soma += (int) $cnpj[$i] * $pesos[$k][$i];
            }
            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $cnpj[$pos] !== $digito) {
                return false;
            }
        }

        return true;
    }

    public static function formatar(?string $valor): string
    {
        $d = self::digitos($valor);

        return match (strlen($d)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d),
            default => (string) $valor,
        };
    }
}
