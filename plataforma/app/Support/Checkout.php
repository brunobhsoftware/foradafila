<?php

namespace App\Support;

use App\Models\Comprador;
use App\Models\ContaPagamento;
use App\Models\Evento;
use App\Models\Ingresso;
use App\Models\Participante;
use App\Models\Pedido;
use App\Pagamentos\MercadoPago;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Venda paga pelo Mercado Pago (Checkout Pro com API de Orders).
 *
 * 1. reservar(): pedido "aguardando_pagamento" com o estoque RESERVADO (lote.reservado) por RESERVA_MIN minutos;
 * 2. criarOrder(): order no Mercado Pago com o token da conta de recebimento do evento (plataforma = BH Software;
 *    propria = aplicação da empresa) e o link de pagamento;
 * 3. sincronizar(): consulta a order (retorno do comprador e webhook) e aprova, mantém pendente ou libera a reserva;
 * 4. aprovar(): reserva vira venda, ingressos numerados e participantes, pagamento aprovado, e-mails. Idempotente.
 * Reservas vencidas são liberadas sem cron (liberarVencidas), quando alguém abre o evento ou compra.
 */
class Checkout
{
    public const RESERVA_MIN = 30;

    /** Token da conta: aplicação da BH (plataforma) ou da própria empresa; em modo de teste, o token de teste. */
    public static function token(ContaPagamento $conta): string
    {
        $teste = self::modoTeste();
        if ($conta->modo === 'propria') {
            $cript = $teste ? $conta->teste_access_token_cript : $conta->access_token_cript;
            if (! $cript) {
                throw new RuntimeException('A aplicação Mercado Pago da organização não tem o Access Token '.($teste ? 'de teste' : 'de produção').'.');
            }

            return decrypt($cript, false);
        }
        $t = $teste ? MercadoPago::tokenTeste() : MercadoPago::tokenAplicacao();
        if (! $t) {
            throw new RuntimeException('A plataforma ainda não está configurada para receber pagamentos.');
        }

        return $t;
    }

    /** Administração → Mercado Pago: "Vendas em modo de teste" (cobra com as credenciais de teste). */
    public static function modoTeste(): bool
    {
        return DB::table('parametro_sistema')->where('chave', 'mp_vendas_teste')->value('valor') === '1';
    }

    public static function contaDoEvento(Evento $evento): ?ContaPagamento
    {
        $conta = Recebimento::contaAtual($evento->organizador_id)
            ?? ($evento->conta_pagamento_id ? ContaPagamento::withoutGlobalScopes()->find($evento->conta_pagamento_id) : null);

        return $conta && $conta->status === 'ativa' ? $conta : null;
    }

    /** Monta e cria a order; grava a linha em pagamento. Devolve o link do checkout. */
    public static function criarOrder(Pedido $pedido, Evento $evento, Comprador $comprador, ContaPagamento $conta, ?string $cpf): string
    {
        $pedido->loadMissing('itens.lote.tipo');
        $volta = fn (string $r) => route('pedido.retorno', ['pedido' => $pedido->uuid, 'resultado' => $r]);
        $partes = preg_split('/\s+/', trim($comprador->nome), 2);
        $fone = preg_replace('/\D/', '', (string) $comprador->telefone);
        $itens = [];
        foreach ($pedido->itens as $item) {
            $repassada = (float) $pedido->valor_taxa_servico > 0;
            $unit = round((float) $item->preco_unitario - (float) $item->desconto_unitario + ($repassada ? (float) $item->taxa_unitaria : 0), 2); // o que o comprador paga por ingresso
            $itens[] = [
                'external_code' => $item->lote->uuid,
                'title' => mb_substr($evento->titulo.' — '.$item->lote->tipo->nome.' ('.$item->lote->nome.')', 0, 250),
                'description' => mb_substr('Ingresso para '.$evento->titulo.' em '.data_local($evento->data_inicio, 'd/m/Y H:i'), 0, 250),
                'category_id' => 'tickets',
                'quantity' => (int) $item->quantidade,
                'unit_price' => number_format($unit, 2, '.', ''),
            ];
        }
        $dados = [
            'type' => 'online',
            'processing_mode' => 'automatic',
            'total_amount' => number_format((float) $pedido->valor_total, 2, '.', ''),
            'external_reference' => $pedido->codigo,
            'description' => mb_substr('Ingressos — '.$evento->titulo, 0, 150),
            'payer' => array_filter([
                'email' => $comprador->email,
                'first_name' => $partes[0],
                'last_name' => $partes[1] ?? $partes[0],
                'identification' => $cpf ? ['type' => 'CPF', 'number' => $cpf] : null,
                'phone' => strlen($fone) >= 10 ? ['area_code' => substr($fone, 0, 2), 'number' => substr($fone, 2)] : null,
            ]),
            'items' => $itens,
            'config' => [
                'statement_descriptor' => 'FORADAFILA',
                'online' => ['success_url' => $volta('sucesso'), 'failure_url' => $volta('falha'), 'pending_url' => $volta('pendente')],
            ],
            'additional_info' => [
                'payer.authentication_type' => 'WEB',
                'payer.registration_date' => \Illuminate\Support\Carbon::parse($comprador->criado_em ?? 'now')->toIso8601String(),
                'payer.is_first_purchase_online' => ! DB::table('pedido')->where('comprador_id', $comprador->id)->where('status', 'pago')->where('valor_total', '>', 0)->exists(),
                'payer.last_purchase' => now()->toIso8601String(),
            ],
        ];
        $order = MercadoPago::criarOrder(self::token($conta), $dados);
        $link = (string) ($order['checkout_url'] ?? data_get($order, 'config.online.checkout_url') ?? '');
        if ($link === '') {
            throw new RuntimeException('O Mercado Pago não devolveu o link de pagamento.');
        }
        DB::table('pagamento')->insert([
            'organizador_id' => $pedido->organizador_id, 'pedido_id' => $pedido->id, 'conta_pagamento_id' => $conta->id,
            'gateway_pedido_id' => (string) $order['id'], 'referencia_externa' => $pedido->codigo,
            'chave_idempotencia' => (string) \Illuminate\Support\Str::uuid(), 'metodo' => 'pix', 'status' => 'criado',
            'status_gateway' => mb_substr((string) ($order['status'] ?? ''), 0, 60), 'valor' => $pedido->valor_total,
            'comissao_retida' => self::comissao($pedido), 'valor_liquido_org' => round((float) $pedido->valor_total - self::comissao($pedido), 2),
            'link_pagamento' => mb_substr($link, 0, 500), 'expira_em' => $pedido->expira_em,
            'resposta_gateway' => json_encode(['ambiente' => self::modoTeste() ? 'teste' : 'producao', 'order' => $order['id']]),
        ]);

        return $link;
    }

    /** Taxa da plataforma do pedido (soma das taxas por ingresso). */
    public static function comissao(Pedido $pedido): float
    {
        return round((float) DB::table('pedido_item')->where('pedido_id', $pedido->id)
            ->selectRaw('COALESCE(SUM(taxa_unitaria * quantidade), 0) AS t')->value('t'), 2);
    }

    /**
     * Consulta a order no Mercado Pago e age conforme o resultado. Devolve 'aprovado', 'pendente' ou 'recusado'.
     */
    public static function sincronizar(Pedido $pedido): string
    {
        if ($pedido->status === 'pago') {
            return 'aprovado';
        }
        $pg = DB::table('pagamento')->where('pedido_id', $pedido->id)->orderByDesc('id')->first();
        if (! $pg || ! $pg->gateway_pedido_id) {
            return 'pendente';
        }
        $conta = ContaPagamento::withoutGlobalScopes()->find($pg->conta_pagamento_id);
        if (! $conta) {
            return 'pendente';
        }
        $ambiente = json_decode((string) $pg->resposta_gateway, true)['ambiente'] ?? 'producao';
        try {
            $token = $ambiente === 'teste'
                ? ($conta->modo === 'propria' ? decrypt((string) $conta->teste_access_token_cript, false) : MercadoPago::tokenTeste())
                : ($conta->modo === 'propria' ? decrypt((string) $conta->access_token_cript, false) : MercadoPago::tokenAplicacao());
            $order = MercadoPago::order((string) $token, $pg->gateway_pedido_id);
        } catch (\Throwable $e) {
            Log::warning('Checkout: não consultou a order '.$pg->gateway_pedido_id.': '.$e->getMessage());

            return 'pendente';
        }
        if (! $order) {
            return 'pendente';
        }
        $status = (string) ($order['status'] ?? '');
        $detalhe = (string) ($order['status_detail'] ?? '');
        $trans = data_get($order, 'transactions.payments.0', []);
        $metodo = match (data_get($trans, 'payment_method.type')) {
            'credit_card', 'debit_card' => 'cartao', 'ticket' => 'boleto', default => 'pix',
        };
        DB::table('pagamento')->where('id', $pg->id)->update([
            'status_gateway' => mb_substr($status, 0, 60), 'status_detalhe' => mb_substr($detalhe, 0, 80),
            'gateway_pagamento_id' => data_get($trans, 'id') ? mb_substr((string) data_get($trans, 'id'), 0, 100) : $pg->gateway_pagamento_id,
            'metodo' => $metodo, 'parcelas' => (int) (data_get($trans, 'payment_method.installments') ?: 1),
        ]);

        if ($status === 'processed' || in_array($detalhe, ['accredited', 'processed'], true)) {
            self::aprovar($pedido->fresh(), $pg->id, $metodo);

            return 'aprovado';
        }
        if (in_array($status, ['failed', 'canceled', 'cancelled', 'expired', 'refunded', 'charged_back'], true)) {
            DB::table('pagamento')->where('id', $pg->id)->update(['status' => $status === 'expired' ? 'expirado' : ($status === 'failed' ? 'recusado' : 'cancelado'),
                'motivo_recusa' => mb_substr($detalhe, 0, 255) ?: null]);
            self::liberar($pedido->fresh(), $status === 'expired' ? 'expirado' : 'recusado');

            return 'recusado';
        }
        DB::table('pagamento')->where('id', $pg->id)->whereIn('status', ['criado'])->update(['status' => 'pendente']);

        return 'pendente';
    }

    /** Pagamento aprovado: reserva vira venda e os ingressos são emitidos (uma vez só). */
    public static function aprovar(Pedido $pedido, int $pagamentoId, string $metodo = 'pix'): void
    {
        $emitiu = DB::transaction(function () use ($pedido, $pagamentoId, $metodo) {
            $p = DB::table('pedido')->where('id', $pedido->id)->lockForUpdate()->first();
            if ($p->status === 'pago') {
                return false;
            }
            $itens = DB::table('pedido_item as pi')->join('lote as l', 'l.id', '=', 'pi.lote_id')
                ->where('pi.pedido_id', $pedido->id)->orderBy('pi.lote_id')
                ->get(['pi.*', 'l.ingresso_tipo_id']);
            $reservado = $p->status === 'aguardando_pagamento';
            foreach ($itens as $it) {
                $ok = $reservado
                    ? DB::update('UPDATE lote SET reservado = reservado - ?, vendido = vendido + ? WHERE id = ? AND reservado >= ?', [$it->quantidade, $it->quantidade, $it->lote_id, $it->quantidade])
                    : DB::update('UPDATE lote SET vendido = vendido + ? WHERE id = ? AND quantidade - reservado - vendido >= ?', [$it->quantidade, $it->lote_id, $it->quantidade]);
                if (! $ok) {
                    // Pago depois de a reserva vencer e o lote esgotar: fica para devolução manual.
                    DB::table('pedido')->where('id', $p->id)->update(['status' => 'cancelado', 'cancelado_em' => now()]);
                    DB::table('pagamento')->where('id', $pagamentoId)->update(['status' => 'aprovado', 'aprovado_em' => now(),
                        'motivo_recusa' => 'Pago sem estoque (reserva vencida): devolver ao comprador']);
                    Auditoria::registrar('pedido.pago_sem_estoque', Pedido::withoutGlobalScopes()->find($p->id));

                    return false;
                }
            }
            $total = (int) $itens->sum('quantidade');
            DB::update('UPDATE evento SET ultimo_numero_ingresso = ultimo_numero_ingresso + ? WHERE id = ?', [$total, $p->evento_id]);
            $numero = (int) DB::table('evento')->where('id', $p->evento_id)->value('ultimo_numero_ingresso') - $total;
            $participantes = json_decode((string) ($p->dados_participantes ?? Cache::get('pedido_participantes_'.$p->id) ?? '[]'), true) ?: [];
            $comprador = Comprador::find($p->comprador_id);
            $i = 0;
            foreach ($itens as $it) {
                $valorUnit = round((float) $it->preco_unitario - (float) $it->desconto_unitario + ((float) $p->valor_taxa_servico > 0 ? (float) $it->taxa_unitaria : 0), 2);
                for ($n = 0; $n < $it->quantidade; $n++, $i++) {
                    $ing = Ingresso::create([
                        'organizador_id' => $p->organizador_id, 'evento_id' => $p->evento_id, 'pedido_id' => $p->id,
                        'pedido_item_id' => $it->id, 'lote_id' => $it->lote_id, 'ingresso_tipo_id' => $it->ingresso_tipo_id,
                        'numero' => ++$numero, 'codigo_qr' => Ingresso::novoCodigoQr(), 'valor_pago' => $valorUnit, 'status' => 'valido',
                    ]);
                    $pp = $participantes[$i] ?? ['nome' => $comprador?->nome ?? 'Participante', 'cpf' => null];
                    $eleMesmo = $i === 0 && $comprador && mb_strtolower($pp['nome']) === mb_strtolower($comprador->nome);
                    Participante::create([
                        'ingresso_id' => $ing->id, 'organizador_id' => $p->organizador_id, 'evento_id' => $p->evento_id,
                        'nome' => $pp['nome'], 'cpf' => $pp['cpf'] ?? null, 'email' => $i === 0 ? $comprador?->email : null,
                        'mesmo_que_comprador' => $eleMesmo,
                    ]);
                    CadastroPessoa::registrar($pp['cpf'] ?? null, $pp['nome'], $eleMesmo ? $comprador->email : null, $eleMesmo ? $comprador->telefone : null, 'compra');
                }
            }
            DB::table('pedido')->where('id', $p->id)->update(['status' => 'pago', 'pago_em' => now(), 'metodo_pagamento' => $metodo]
                + (Esquema::temV013() ? ['dados_participantes' => null] : []));
            DB::table('pagamento')->where('id', $pagamentoId)->update(['status' => 'aprovado', 'aprovado_em' => now()]);
            if ($p->cupom_id) {
                DB::table('cupom_uso')->where('pedido_id', $p->id)->update(['status' => 'confirmado']);
            }
            Auditoria::registrar('pedido.pago', Pedido::withoutGlobalScopes()->find($p->id));

            return true;
        });

        if ($emitiu) {
            $pedido = Pedido::withoutGlobalScopes()->with(['ingressos.participante', 'ingressos.tipo', 'ingressos.lote'])->find($pedido->id);
            $evento = Evento::withoutGlobalScopes()->with(['local', 'organizador'])->find($pedido->evento_id);
            $comprador = Comprador::find($pedido->comprador_id);
            Notificacoes::ingressosConfirmados($pedido, $comprador, $evento, $evento->organizador, $pedido->ingressos);
            Notificacoes::novaCompraParaEquipe($pedido, $comprador, $evento, $evento->organizador, $pedido->ingressos->count());
        }
    }

    /** Devolve a reserva ao estoque (pagamento recusado, cancelado ou reserva vencida). */
    public static function liberar(Pedido $pedido, string $status = 'expirado'): void
    {
        DB::transaction(function () use ($pedido, $status) {
            $ok = DB::update("UPDATE pedido SET status = ?, cancelado_em = UTC_TIMESTAMP() WHERE id = ? AND status = 'aguardando_pagamento'", [$status, $pedido->id]);
            if (! $ok) {
                return;
            }
            foreach (DB::table('pedido_item')->where('pedido_id', $pedido->id)->orderBy('lote_id')->get() as $it) {
                DB::update('UPDATE lote SET reservado = GREATEST(reservado - ?, 0) WHERE id = ?', [$it->quantidade, $it->lote_id]);
            }
            if ($pedido->cupom_id) {
                DB::update('UPDATE cupom SET usos = GREATEST(usos - 1, 0) WHERE id = ?', [$pedido->cupom_id]);
                DB::table('cupom_uso')->where('pedido_id', $pedido->id)->update(['status' => 'estornado']);
            }
        });
    }

    /** Reservas vencidas do evento: confere no Mercado Pago (Pix pode ter sido pago) e libera as que não foram pagas. */
    public static function liberarVencidas(int $eventoId): void
    {
        $vencidos = Pedido::withoutGlobalScopes()->where('evento_id', $eventoId)->where('status', 'aguardando_pagamento')
            ->where('expira_em', '<', now())->limit(20)->get();
        foreach ($vencidos as $p) {
            if (self::sincronizar($p) === 'pendente') {
                self::liberar($p->fresh(), 'expirado');
            }
        }
    }
}
