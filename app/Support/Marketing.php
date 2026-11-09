<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Recurso de e-mail marketing por empresa (decisão do Bruno, 09/11/2026): a Administração liga/desliga o envio de convites
 * por empresa e define o limite diário (padrão 500). Desligado por padrão — a empresa só envia depois de contratar, para
 * não sobrecarregar os servidores. Guardado em parametro_sistema (marketing_org_<id>_ativo / _limite), sem mudar o banco.
 */
class Marketing
{
    public const LIMITE_PADRAO = 500;

    public const LIMITE_MAXIMO = 5000;

    public static function ativo(int $orgId): bool
    {
        return DB::table('parametro_sistema')->where('chave', "marketing_org_{$orgId}_ativo")->value('valor') === '1';
    }

    public static function limiteDia(int $orgId): int
    {
        $v = (int) DB::table('parametro_sistema')->where('chave', "marketing_org_{$orgId}_limite")->value('valor');

        return $v > 0 ? min($v, self::LIMITE_MAXIMO) : self::LIMITE_PADRAO;
    }

    public static function definir(int $orgId, bool $ativo, int $limite): void
    {
        $limite = max(1, min($limite, self::LIMITE_MAXIMO));
        DB::table('parametro_sistema')->updateOrInsert(['chave' => "marketing_org_{$orgId}_ativo"],
            ['valor' => $ativo ? '1' : '0', 'descricao' => 'E-mail marketing liberado para a empresa']);
        DB::table('parametro_sistema')->updateOrInsert(['chave' => "marketing_org_{$orgId}_limite"],
            ['valor' => (string) $limite, 'descricao' => 'Limite diário de convites por e-mail da empresa']);
    }
}
