<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Preferência da organização (decisão do Bruno, 09/11/2026): receber CÓPIA (Cc) do e-mail de ingressos que vai ao comprador
 * — um e-mail só, para o comprador, com cópia para o e-mail de contato da organização. Guardada em parametro_sistema
 * (copia_ingressos_org_<id> = 1), sem mudar o banco.
 */
class CopiaIngressos
{
    private static function chave(int $orgId): string
    {
        return 'copia_ingressos_org_'.$orgId;
    }

    public static function ativa(?int $orgId): bool
    {
        return $orgId !== null && DB::table('parametro_sistema')->where('chave', self::chave($orgId))->where('valor', '1')->exists();
    }

    public static function definir(int $orgId, bool $ativa): void
    {
        DB::table('parametro_sistema')->updateOrInsert(['chave' => self::chave($orgId)],
            ['valor' => $ativa ? '1' : '0', 'descricao' => 'Organização recebe cópia dos e-mails de ingressos']);
    }
}
