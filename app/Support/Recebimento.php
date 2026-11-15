<?php

namespace App\Support;

use App\Models\ContaPagamento;
use App\Models\Organizador;
use Illuminate\Support\Facades\DB;

/**
 * Recebimento pela plataforma (V012): as vendas pagas caem na conta do Mercado Pago da BH Software, que retém a taxa
 * e repassa o restante ao produtor por Pix ou conta bancária. A conta "modo plataforma" em conta_pagamento atende o
 * gatilho de publicação (lote pago exige conta ativa em produção) sem tokens do produtor.
 */
class Recebimento
{
    public const PIX_TIPOS = ['cpf' => 'CPF', 'cnpj' => 'CNPJ', 'email' => 'E-mail', 'telefone' => 'Celular', 'aleatoria' => 'Chave aleatória'];

    /** Prazo do repasse, em dias úteis depois do fim do evento (decisão do Bruno: 2 a 3 dias). */
    public const PRAZO_DIAS_UTEIS = 3;

    public static function temPix(Organizador $org): bool
    {
        return Esquema::temV012() && $org->pix_tipo && $org->pix_chave;
    }

    public static function temConta(Organizador $org): bool
    {
        return $org->banco && $org->agencia && $org->conta && $org->favorecido;
    }

    public static function completo(Organizador $org): bool
    {
        return self::temPix($org) || self::temConta($org);
    }

    /** Texto do destino do repasse (fica gravado no repasse). */
    public static function destino(Organizador $org): ?string
    {
        $pix = self::temPix($org) ? 'Pix '.(self::PIX_TIPOS[$org->pix_tipo] ?? '').': '.$org->pix_chave : null;
        $conta = self::temConta($org) ? "{$org->banco} · ag. {$org->agencia} · conta {$org->conta} · {$org->favorecido}" : null;
        $pref = Esquema::temV012() ? $org->recebimento_preferido : null;

        return ($pref === 'conta' ? ($conta ?? $pix) : ($pix ?? $conta));
    }

    public static function conta(int $organizadorId, string $modo = 'plataforma'): ?ContaPagamento
    {
        if (! Esquema::temV012()) {
            return null;
        }

        return ContaPagamento::withoutGlobalScopes()->where('organizador_id', $organizadorId)->where('modo', $modo)->first();
    }

    /** Preferência de configuração do MP: 'automatica' (OAuth) ou 'manual'. */
    public static function configMp(int $organizadorId): string
    {
        return DB::table('parametro_sistema')->where('chave', 'mp_config_org_'.$organizadorId)->value('valor') ?: 'automatica';
    }

    /** Conta usada nas vendas: respeita a preferência automática/manual da empresa. */
    public static function contaAtual(int $organizadorId): ?ContaPagamento
    {
        if (! Esquema::temV012()) {
            return null;
        }
        $modo = Organizador::whereKey($organizadorId)->value('recebimento_modo') ?: 'plataforma';
        if ($modo === 'propria') {
            $config = self::configMp($organizadorId);
            if ($config === 'manual') {
                $propria = self::conta($organizadorId, 'propria');
                if ($propria && $propria->status === 'ativa') {
                    return $propria;
                }
                $direta = self::conta($organizadorId, 'direta');

                return ($direta && $direta->status === 'ativa') ? $direta : $propria;
            }
            $direta = self::conta($organizadorId, 'direta');
            if ($direta && $direta->status === 'ativa') {
                return $direta;
            }

            return self::conta($organizadorId, 'propria');
        }

        return self::conta($organizadorId, 'plataforma');
    }

    /** Aplicação Mercado Pago da própria empresa (cria o registro vazio na primeira vez). */
    public static function contaPropria(Organizador $org): ContaPagamento
    {
        $conta = self::conta($org->id, 'propria');
        if (! $conta) {
            $conta = new ContaPagamento();
            $conta->forceFill([
                'organizador_id' => $org->id, 'gateway_id' => DB::table('gateway')->where('codigo', 'mercadopago')->value('id'),
                'apelido' => 'Mercado Pago da empresa (aplicação própria)', 'modo' => 'propria', 'ambiente' => 'producao',
                'status' => 'pendente', 'descricao_fatura' => 'FORADAFILA',
            ])->save();
        }

        return $conta;
    }

    /** Cria/ativa (ou suspende, sem dados) a conta "Recebimento pela plataforma" da organização. */
    public static function sincronizar(Organizador $org): ?ContaPagamento
    {
        if (! Esquema::temV012()) {
            return null;
        }
        $conta = self::conta($org->id);
        $status = self::completo($org) ? 'ativa' : 'suspensa';
        if (! $conta) {
            $conta = new ContaPagamento();
            $conta->forceFill([
                'organizador_id' => $org->id,
                'gateway_id' => DB::table('gateway')->where('codigo', 'mercadopago')->value('id'),
                'apelido' => 'Recebimento pela plataforma', 'modo' => 'plataforma', 'ambiente' => 'producao',
                'descricao_fatura' => 'FORADAFILA', 'padrao' => 1,
            ]);
        }
        // O gatilho trg_conta_bu só ativa conta homologada: aqui não há OAuth a homologar, os dados de repasse bastam.
        $conta->forceFill(['status' => $status] + ($status === 'ativa' ? ['homologada_em' => $conta->homologada_em ?? now()] : []))->save();

        return $conta;
    }
}
