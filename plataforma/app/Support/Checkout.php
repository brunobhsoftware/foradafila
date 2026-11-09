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
        $teste = self::modoTeste($conta->organizador_id);
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

    /** Vendas em homologação da empresa (Financeiro → "Ambiente das vendas"): o checkout usa as credenciais de TESTE. */
    public static function modoTeste(?int $organizadorId = null): bool
    {
        // Só a empresa decide (Financeiro → Ambiente das vendas); não há mais chave geral na Administração (decisão do Bruno, 08/11/2026).
        return $organizadorId !== null && DB::table('parametro_sistema')->where('chave', 'mp_vendas_teste_org_'.$organizadorId)->where('valor', '1')->exists();
    }

    /** Token da conta conforme o ambiente em que o pagamento foi feito ('teste' ou 'producao'). */
    public static function tokenDoAmbiente(ContaPagamento $conta, string $ambiente): string
    {
        $t = $ambiente === 'teste'
            ? ($conta->modo === 'propria' ? ($conta->teste_access_token_cript ? decrypt($conta->teste_access_token_cript, false) : null) : MercadoPago::tokenTeste())
            : ($conta->modo === 'propria' ? ($conta->access_token_cript ? decrypt($conta->access_token_cript, false) : null) : MercadoPago::tokenAplicacao());
        if (! $t) {
            throw new RuntimeException('Sem o Access Token '.($ambiente === 'teste' ? 'de teste' : 'de produção').' da conta de recebimento.');
        }

        return $t;
    }

    /**
     * Devolve ao comprador pelo Mercado Pago (cancelamento pela organização). Devolve o que foi devolvido;
     * null quando o pedido não foi pago pelo checkout (aí a devolução é manual, como antes).
     */
    public static function reembolsar(Pedido $pedido, float $valor): ?array
    {
        $pg = DB::table('pagamento')->where('pedido_id', $pedido->id)->where('status', 'aprovado')->whereNotNull('gateway_pedido_id')->orderByDesc('id')->first();
        if (! $pg || $valor <= 0) {
            return null;
        }
        $conta = ContaPagamento::withoutGlobalScopes()->findOrFail($pg->conta_pagamento_id);
        $ambiente = json_decode((string) $pg->resposta_gateway, true)['ambiente'] ?? 'producao';
        $total = round($valor, 2) >= round((float) $pg->valor, 2);
        $corpo = $total || ! $pg->gateway_pagamento_id ? null
            : ['transactions' => [['id' => $pg->gateway_pagamento_id, 'amount' => number_format($valor, 2, '.', '')]]];
        $r = MercadoPago::reembolsarOrder(self::tokenDoAmbiente($conta, $ambiente), $pg->gateway_pedido_id, $corpo);
        DB::table('pagamento')->where('id', $pg->id)->update(['status' => $total ? 'estornado' : 'estorno_parcial',
            'status_gateway' => mb_substr((string) ($r['status'] ?? ''), 0, 60)]);
        Auditoria::registrar('pedido.reembolso_mercadopago', $pedido, null, ['valor' => $valor, 'total' => $total, 'order' => $pg->gateway_pedido_id, 'ambiente' => $ambiente]);

        return ['valor' => $valor, 'ambiente' => $ambiente];
    }

    /** Public Key da conta de recebimento no ambiente das vendas da empresa (formulário de cartão na nossa página). */
    public static function publicKey(ContaPagamento $conta): ?string
    {
        $teste = self::modoTeste($conta->organizador_id);
        if ($conta->modo === 'propria') {
            return ($teste ? $conta->teste_public_key : $conta->public_key) ?: null;
        }

        return MercadoPago::credencial($teste ? 'teste_public_key' : 'public_key') ?: null;
    }

    /** Última order criada (para o cartão direto saber na hora se aprovou). */
    public static ?array $ultimaOrder = null;

    /**
     * Cartão na nossa página (Card Payment Brick, decisão do Bruno 09/11/2026): o formulário do Mercado Pago gera o token do
     * cartão; aqui a order é criada e processada na hora. Devolve 'aprovado', 'pendente' ou a mensagem de recusa.
     */
    public static function pagarCartao(Pedido $pedido, Evento $evento, Comprador $comprador, ContaPagamento $conta, array $f): string
    {
        $cpf = preg_replace('/\D/', '', (string) data_get($f, 'payer.identification.number', '')) ?: $comprador->cpf;
        self::criarOrder($pedido, $evento, $comprador, $conta, $cpf, 'cartao', [
            'id' => (string) $f['payment_method_id'], 'type' => (string) ($f['payment_type_id'] ?? 'credit_card'), 'token' => (string) $f['token'],
            'installments' => max(1, (int) ($f['installments'] ?? 1)), 'issuer_id' => $f['issuer_id'] ?? null,
            'email' => data_get($f, 'payer.email'),
        ]);
        if ($cpf && strlen($cpf) === 11 && ! $comprador->cpf) {
            $comprador->forceFill(['cpf' => $cpf])->save();
            CadastroPessoa::registrar($cpf, $comprador->nome, $comprador->email, $comprador->telefone, 'compra');
        }
        $o = self::$ultimaOrder ?? [];
        $status = (string) ($o['status'] ?? ''); $detalhe = (string) ($o['status_detail'] ?? data_get($o, 'transactions.payments.0.status_detail', ''));
        if ($status === 'processed' || in_array($detalhe, ['accredited', 'processed'], true)) {
            return self::sincronizar($pedido->fresh());
        }
        if ($status === 'failed' || in_array($detalhe, ['rejected', 'cc_rejected_other_reason'], true) || str_starts_with($detalhe, 'cc_rejected') || str_starts_with($detalhe, 'rejected')) {
            // Recusa: o pedido continua reservado para tentar outro cartão.
            DB::table('pagamento')->where('pedido_id', $pedido->id)->orderByDesc('id')->limit(1)
                ->update(['status' => 'recusado', 'status_gateway' => mb_substr($status, 0, 60), 'motivo_recusa' => mb_substr($detalhe, 0, 255) ?: null]);

            return self::mensagemRecusa($detalhe);
        }

        return 'pendente';
    }

    public static function mensagemRecusa(string $detalhe): string
    {
        return match (true) {
            str_contains($detalhe, 'insufficient') => 'Cartão sem limite suficiente. Tente outro cartão ou pague com Pix.',
            str_contains($detalhe, 'security_code') => 'Código de segurança (CVV) incorreto.',
            str_contains($detalhe, 'date') || str_contains($detalhe, 'expir') => 'Data de validade incorreta.',
            str_contains($detalhe, 'call_for_authorize') => 'O banco pediu autorização: ligue para o seu banco ou use outro cartão.',
            str_contains($detalhe, 'duplicated') => 'Pagamento duplicado: confira se já não foi aprovado.',
            default => 'O cartão foi recusado. Confira os dados, tente outro cartão ou pague com Pix.',
        };
    }

    public static function contaDoEvento(Evento $evento): ?ContaPagamento
    {
        $conta = Recebimento::contaAtual($evento->organizador_id)
            ?? ($evento->conta_pagamento_id ? ContaPagamento::withoutGlobalScopes()->find($evento->conta_pagamento_id) : null);

        return $conta && $conta->status === 'ativa' ? $conta : null;
    }

    /** Monta e cria a order; grava a linha em pagamento. Devolve o link do checkout. */
    public static function criarOrder(Pedido $pedido, Evento $evento, Comprador $comprador, ContaPagamento $conta, ?string $cpf, ?string $forma = null, ?array $cartao = null): string
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
                'external_code' => substr(str_replace('-', '', $item->lote->uuid), 0, 30), // o MP aceita até 30 caracteres
                'title' => mb_substr($evento->titulo.' — '.$item->lote->tipo->nome.' ('.$item->lote->nome.')', 0, 250),
                'description' => mb_substr('Ingresso para '.$evento->titulo.' em '.data_local($evento->data_inicio, 'd/m/Y H:i'), 0, 250),
                'category_id' => 'tickets',
                'quantity' => (int) $item->quantidade,
                'unit_price' => number_format($unit, 2, '.', ''),
            ];
        }
        $dados = [
            'type' => 'online',
            'processing_mode' => 'manual',
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
                // Forma escolhida na nossa tela: Pix vai direto ao Pix; cartão mostra só cartões.
                'payment_method' => match ($forma) {
                    'pix' => ['not_allowed_types' => ['credit_card', 'debit_card', 'prepaid_card', 'ticket', 'atm'], 'default_id' => 'pix'],
                    'cartao' => ['not_allowed_types' => ['bank_transfer', 'ticket', 'atm']],
                    default => null,
                },
                'online' => ['success_url' => $volta('sucesso'), 'failure_url' => $volta('falha'), 'pending_url' => $volta('pendente'),
                    // 3DS (Qualidade da integração): valida quando há risco de fraude e transfere a responsabilidade ao emissor
                    'transaction_security' => ['validation' => 'on_fraud_risk', 'liability_shift' => 'required']],
            ],
            'additional_info' => [
                'payer.authentication_type' => 'WEB',
                'payer.registration_date' => \Illuminate\Support\Carbon::parse($comprador->criado_em ?? 'now')->toIso8601String(),
                'payer.is_first_purchase_online' => ! DB::table('pedido')->where('comprador_id', $comprador->id)->where('status', 'pago')->where('valor_total', '>', 0)->exists(),
                'payer.last_purchase' => now()->toIso8601String(),
            ],
        ];
        if (! $forma) {
            unset($dados['config']['payment_method']);
        }
        $pix = null;
        if ($forma === 'pix') {
            // Pix direto (sem a tela do Mercado Pago, decisão do Bruno 09/11/2026): a order já cria o pagamento Pix e devolve
            // o "copia e cola"; o QR Code é desenhado na nossa página (/pedido/{uuid}/pix), que confere sozinha o pagamento.
            $dados['processing_mode'] = 'automatic';
            unset($dados['config']['payment_method'], $dados['config']['online']);
            $dados['transactions'] = ['payments' => [[
                'amount' => $dados['total_amount'],
                'payment_method' => ['id' => 'pix', 'type' => 'bank_transfer'],
                'expiration_time' => 'PT'.self::RESERVA_MIN.'M',
            ]]];
        }
        if ($cartao) {
            // Cartão direto: token do formulário do Mercado Pago; processa na hora (sem a tela do Mercado Pago).
            $dados['processing_mode'] = 'automatic';
            unset($dados['config']['payment_method'], $dados['config']['online']);
            if (! empty($cartao['email'])) {
                $dados['payer']['email'] = (string) $cartao['email'];
            }
            $dados['transactions'] = ['payments' => [[
                'amount' => $dados['total_amount'],
                'payment_method' => array_filter(['id' => $cartao['id'], 'type' => $cartao['type'], 'token' => $cartao['token'],
                    'installments' => $cartao['installments']]),
            ]]];
        }
        $order = MercadoPago::criarOrder(self::token($conta), $dados);
        self::$ultimaOrder = $order;
        if ($cartao) {
            $link = route('pedido.retorno', ['pedido' => $pedido->uuid]);
        } elseif ($forma === 'pix') {
            $pm = (array) data_get($order, 'transactions.payments.0.payment_method', []);
            $pix = ['qr' => (string) ($pm['qr_code'] ?? ''), 'ticket' => (string) ($pm['ticket_url'] ?? '')];
            if ($pix['qr'] === '') {
                throw new RuntimeException('O Mercado Pago não devolveu o código do Pix.');
            }
            $link = route('pedido.pix', $pedido->uuid);
        } else {
            $link = (string) ($order['checkout_url'] ?? data_get($order, 'config.online.checkout_url') ?? '');
        }
        if ($link === '') {
            throw new RuntimeException('O Mercado Pago não devolveu o link de pagamento.');
        }
        DB::table('pagamento')->insert([
            'organizador_id' => $pedido->organizador_id, 'pedido_id' => $pedido->id, 'conta_pagamento_id' => $conta->id,
            'gateway_pedido_id' => (string) $order['id'], 'referencia_externa' => $pedido->codigo,
            'chave_idempotencia' => (string) \Illuminate\Support\Str::uuid(), 'metodo' => $forma === 'cartao' ? 'cartao' : 'pix', 'status' => 'criado',
            'status_gateway' => mb_substr((string) ($order['status'] ?? ''), 0, 60), 'valor' => $pedido->valor_total,
            'comissao_retida' => self::comissao($pedido), 'valor_liquido_org' => round((float) $pedido->valor_total - self::comissao($pedido), 2),
            'link_pagamento' => mb_substr($link, 0, 500), 'expira_em' => $pedido->expira_em,
            'resposta_gateway' => json_encode(['ambiente' => self::modoTeste($pedido->organizador_id) ? 'teste' : 'producao', 'order' => $order['id']] + ($pix ? ['pix' => $pix] : [])),
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
            $token = self::tokenDoAmbiente($conta, $ambiente);
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
            $eventoPedido = Evento::withoutGlobalScopes()->find($p->evento_id);
            $campos = Formularios::ativo() ? Formularios::campos(Formularios::doEvento($eventoPedido)) : [];
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
                    Formularios::criarParticipante($p->organizador_id, $p->evento_id, $ing->id, $pp, $campos, $comprador, $i, 'compra');
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
            // Webhook/retorno do Pix não têm organizador na sessão: sem isto o escopo por organizador esconde os ingressos
            // e o e-mail sairia sem a lista e sem o PDF.
            $evento = Evento::withoutGlobalScopes()->with(['organizador'])->find($pedido->evento_id);
            app(Tenant::class)->definir($evento->organizador);
            $evento->load('local');
            $pedido = Pedido::withoutGlobalScopes()->with(['ingressos.participante', 'ingressos.tipo', 'ingressos.lote'])->find($pedido->id);
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
