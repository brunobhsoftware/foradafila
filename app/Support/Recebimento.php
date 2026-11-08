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

    /** Conta usada nas vendas: a aplicação própria (se escolhida e ativa) ou o recebimento pela plataforma. */
    public static function contaAtual(int $organizadorId): ?ContaPagamento
    {
        if (! Esquema::temV012()) {
            return null;
        }
        $modo = Organizador::whereKey($organizadorId)->value('recebimento_modo') ?: 'plataforma';

        return self::conta($organizadorId, $modo === 'propria' ? 'propria' : 'plataforma');
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
