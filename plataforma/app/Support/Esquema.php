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

    /** V010: quem paga a taxa da plataforma (organizador.taxa_comprador, evento.taxa_comprador). */
    public static function temV010(): bool
    {
        return self::$cache['v010'] ??= Schema::hasColumn('organizador', 'taxa_comprador') && Schema::hasColumn('evento', 'taxa_comprador');
    }

    /** V014: lote.exibir (mostrar/ocultar o ingresso na página do evento). */
    public static function temV014(): bool
    {
        return self::$cache['v014'] ??= Schema::hasColumn('lote', 'exibir');
    }

    /** V013: participantes guardados no pedido até o pagamento ser aprovado. */
    public static function temV013(): bool
    {
        return self::$cache['v013'] ??= Schema::hasColumn('pedido', 'dados_participantes');
    }

    /** V012: recebimento pela plataforma (Pix/conta do produtor, conta_pagamento.modo, repasse). */
    public static function temV012(): bool
    {
        return self::$cache['v012'] ??= Schema::hasColumn('conta_pagamento', 'modo') && Schema::hasTable('repasse');
    }

    /** V012 completo: modo "propria" (aplicação Mercado Pago da empresa) e os campos das chaves. */
    public static function temPropria(): bool
    {
        return self::$cache['propria'] ??= self::temV012() && Schema::hasColumn('conta_pagamento', 'teste_access_token_cript')
            && str_contains((string) (\Illuminate\Support\Facades\DB::selectOne("SHOW COLUMNS FROM conta_pagamento LIKE 'modo'")->Type ?? ''), 'propria');
    }

    /** V011: cadastro único de pessoas por CPF. */
    public static function temV011(): bool
    {
        return self::$cache['v011'] ??= Schema::hasTable('pessoa');
    }

    /** V009: cupom do tipo "divulgacao". */
    public static function temV009(): bool
    {
        return self::$cache['v009'] ??= str_contains((string) (\Illuminate\Support\Facades\DB::selectOne("SHOW COLUMNS FROM cupom LIKE 'tipo_desconto'")->Type ?? ''), 'divulgacao');
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
