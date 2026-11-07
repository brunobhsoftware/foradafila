<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;

/** Módulos das próximas fases. O banco já tem as tabelas; a tela entra em seguida. */
class EmBreveController extends Controller
{
    public const MODULOS = [
        'vendas' => ['titulo' => 'Vendas e pedidos', 'permissao' => 'pedido.ver',
            'texto' => 'Pedidos, compradores, emissão manual de cortesias e reembolsos.', 'tabelas' => 'pedido, pedido_item, comprador, reembolso'],
        'participantes' => ['titulo' => 'Participantes', 'permissao' => 'participante.ver',
            'texto' => 'Lista de participantes por evento com número do ingresso, lote, comprador e check-in, com filtros e exportação.', 'tabelas' => 'ingresso, participante, vw_participantes'],
        'checkin' => ['titulo' => 'Check-in', 'permissao' => 'checkin.realizar',
            'texto' => 'Leitura de QR Code pelo navegador ou aplicativo (inclusive sem internet), portões e acompanhamento da lotação.', 'tabelas' => 'checkin_ponto, dispositivo_checkin, checkin_log'],
        'cupons' => ['titulo' => 'Cupons e promoters', 'permissao' => 'cupom.gerenciar',
            'texto' => 'Cupons de desconto e links rastreáveis para promoters, com cupom automático.', 'tabelas' => 'cupom, cupom_lote, promoter'],
        'convidados' => ['titulo' => 'Convidados e acesso privado', 'permissao' => 'convidado.gerenciar',
            'texto' => 'Listas de convidados e links de acesso privado para grupos de divulgação.', 'tabelas' => 'lista_convidados, convidado, acesso_privado'],
        'mensagens' => ['titulo' => 'Mensagens', 'permissao' => 'mensagem.gerenciar',
            'texto' => 'Texto do e-mail de confirmação da compra, lembretes e demais mensagens, por organizador ou por evento.', 'tabelas' => 'modelo_mensagem, mensagem_envio'],
        'financeiro' => ['titulo' => 'Financeiro', 'permissao' => 'financeiro.ver',
            'texto' => 'Contas de recebimento (Mercado Pago primeiro), homologação, pagamentos, comissão e faturas.', 'tabelas' => 'conta_pagamento, pagamento, taxa_lancamento, fatura'],
    ];

    public function __invoke(string $modulo)
    {
        $info = self::MODULOS[$modulo];
        abort_unless(auth()->user()->can($info['permissao']), 403);

        return view('painel.em-breve', compact('info', 'modulo'));
    }
}
