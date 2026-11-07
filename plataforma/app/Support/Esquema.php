<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Confere se os scripts de banco mais novos já foram importados em produção.
 * Sem SSH, os scripts banco/V0xx são importados à mão no phpMyAdmin: até lá, as telas
 * que dependem das colunas novas avisam em vez de dar erro.
 */
class Esquema
{
    private static array $cache = [];

    /** V004: representante legal, dados bancários, chave da API, eventos passados e preferências de notificação. */
    public static function temV004(): bool
    {
        return self::$cache['v004'] ??= Schema::hasColumn('organizador', 'representante_cpf')
            && Schema::hasColumn('organizador', 'mostrar_eventos_passados')
            && Schema::hasColumn('organizador_usuario', 'notificar_compra');
    }

    /** V008: motor de taxas e "assumir conta" (impersonação) da Administração. */
    public static function temV008(): bool
    {
        return self::$cache['v008'] ??= Schema::hasTable('impersonacao_log') && Schema::hasColumn('auditoria', 'admin_impersonando_id');
    }

    /** V005: evento.capacidade aceita NULL (capacidade máxima opcional). */
    public static function capacidadeOpcional(): bool
    {
        if (! isset(self::$cache['v005'])) {
            $coluna = collect(Schema::getColumns('evento'))->firstWhere('name', 'capacidade');
            self::$cache['v005'] = (bool) ($coluna['nullable'] ?? false);
        }

        return self::$cache['v005'];
    }
}
