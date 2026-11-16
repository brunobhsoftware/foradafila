<?php

namespace App\Support;

/**
 * Nome de pessoa padronizado em todo o sistema (decisão do Bruno, 16/11/2026): primeira letra de cada palavra maiúscula, o resto
 * minúsculo, e "de, da, do, das, dos, e, di, du" minúsculos no meio ("BRUNO DE MAGALHÃES" → "Bruno de Magalhães").
 * Usado pelos models (Usuario, Comprador, Participante, Organizador.representante_nome), cadastro único, convidados e listas de e-mail.
 * O mesmo algoritmo está em banco/V022__nomes_padronizados.sql para corrigir o que já existe.
 */
class NomePessoa
{
    public const MINUSCULAS = ['de', 'da', 'do', 'das', 'dos', 'e', 'di', 'du', "d'"];

    public static function padronizar(?string $nome): ?string
    {
        if ($nome === null) {
            return null;
        }
        $nome = trim(preg_replace('/\s+/u', ' ', $nome));
        if ($nome === '') {
            return '';
        }
        $palavras = explode(' ', mb_strtolower($nome));
        foreach ($palavras as $i => $p) {
            if ($i > 0 && in_array($p, self::MINUSCULAS, true)) {
                continue;
            }
            // Também depois de hífen e apóstrofo: "Ana-Maria", "D'Ávila".
            $palavras[$i] = preg_replace_callback("/(^|[-'’])(\\p{L})/u", fn ($m) => $m[1].mb_strtoupper($m[2]), $p);
        }

        return implode(' ', $palavras);
    }

    /** Pelo menos nome e sobrenome (duas palavras com 2+ letras, sem contar de/da/do...). */
    public static function completo(?string $nome): bool
    {
        $palavras = array_filter(explode(' ', mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $nome)))),
            fn ($p) => ! in_array($p, self::MINUSCULAS, true) && mb_strlen(preg_replace('/[^\p{L}]/u', '', $p)) >= 2);

        return count($palavras) >= 2;
    }
}
