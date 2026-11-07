<?php

namespace App\Support;

use App\Models\Comprador;
use App\Models\Evento;
use App\Models\Ingresso;
use App\Models\Lote;
use App\Models\Participante;
use App\Models\Pedido;
use App\Models\PedidoItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Emissão de ingressos sem cobrança pela organização (convidados, cortesias, adicionados manualmente).
 * Usa qualquer lote do evento (inclusive "adicionar manualmente"), baixa o estoque e numera como uma venda.
 */
class EmissaoCortesia
{
    /** @param string[] $nomes um ingresso por nome (o primeiro é o titular) */
    public static function emitir(Evento $evento, Lote $lote, string $email, array $nomes, string $origem = 'emissao_manual', ?string $telefone = null): Pedido
    {
        $nomes = array_values(array_filter(array_map('trim', $nomes)));
        $total = count($nomes);
        if ($total < 1) {
            throw ValidationException::withMessages(['nome' => 'Informe o nome do convidado.']);
        }

        return DB::transaction(function () use ($evento, $lote, $email, $nomes, $total, $origem, $telefone) {
            $comprador = Comprador::firstOrCreate(['email' => mb_strtolower($email)], ['nome' => $nomes[0],
                'telefone' => $telefone ? preg_replace('/\D/', '', $telefone) : null]);

            $ok = DB::update('UPDATE lote SET vendido = vendido + ? WHERE id = ? AND evento_id = ? AND quantidade - reservado - vendido >= ?',
                [$total, $lote->id, $evento->id, $total]);
            if (! $ok) {
                throw ValidationException::withMessages(['lote' => "O lote “{$lote->nome}” não tem {$total} ingresso(s) disponível(is). Aumente a quantidade do lote."]);
            }
            DB::update('UPDATE evento SET ultimo_numero_ingresso = ultimo_numero_ingresso + ? WHERE id = ?', [$total, $evento->id]);
            $numero = (int) DB::table('evento')->where('id', $evento->id)->value('ultimo_numero_ingresso') - $total;

            $pedido = Pedido::create([
                'codigo' => Pedido::novoCodigo(), 'organizador_id' => $evento->organizador_id, 'evento_id' => $evento->id,
                'comprador_id' => $comprador->id, 'origem' => $origem, 'chave_idempotencia' => (string) Str::uuid(),
                'status' => 'pago', 'valor_bruto' => 0, 'valor_desconto' => 0, 'valor_total' => 0,
                'metodo_pagamento' => 'gratuito', 'ip' => request()->ip(), 'expira_em' => now(), 'pago_em' => now(),
            ]);
            $item = PedidoItem::create(['pedido_id' => $pedido->id, 'lote_id' => $lote->id, 'quantidade' => $total,
                'preco_unitario' => 0, 'desconto_unitario' => 0, 'subtotal' => 0]);
            foreach ($nomes as $i => $nome) {
                $ingresso = Ingresso::create([
                    'organizador_id' => $evento->organizador_id, 'evento_id' => $evento->id, 'pedido_id' => $pedido->id,
                    'pedido_item_id' => $item->id, 'lote_id' => $lote->id, 'ingresso_tipo_id' => $lote->ingresso_tipo_id,
                    'numero' => ++$numero, 'codigo_qr' => Ingresso::novoCodigoQr(), 'valor_pago' => 0, 'status' => 'valido',
                ]);
                Participante::create(['ingresso_id' => $ingresso->id, 'organizador_id' => $evento->organizador_id, 'evento_id' => $evento->id,
                    'nome' => mb_substr($nome, 0, 150), 'email' => $i === 0 ? $comprador->email : null]);
            }
            Auditoria::registrar('pedido.cortesia.emitir', $pedido, null, ['origem' => $origem, 'lote' => $lote->nome, 'nomes' => $nomes]);

            return $pedido->setRelation('comprador', $comprador);
        });
    }
}
