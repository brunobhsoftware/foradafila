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

    /**
     * Nome de empresa (razão social / nome fantasia) padronizado (decisão do Bruno, 18/11/2026): mesmo padrão dos nomes de pessoa
     * ("CHOPERIA DO ZÉ LTDA" → "Choperia do Zé Ltda"), com ME, EPP, MEI, EIRELI e S/A em maiúsculas e palavras com números intactas.
     */
    public static function padronizarEmpresa(?string $nome): ?string
    {
        $original = $nome === null ? null : preg_split('/\s+/u', trim($nome));
        $nome = self::padronizar($nome);
        if ($nome === null || $nome === '') {
            return $nome;
        }
        $palavras = explode(' ', $nome);
        foreach ($palavras as $i => $p) {
            $min = mb_strtolower($p);
            if (in_array($min, ['me', 'epp', 'mei', 'eireli', 's/a', 's.a.', 's.a', 'sa'], true) && $i > 0) {
                $palavras[$i] = mb_strtoupper($p);
            } elseif (preg_match('/\d/', $p) && isset($original[$i])) {
                $palavras[$i] = $original[$i]; // "3D", "2B Eventos": mantém como digitado
            }
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
