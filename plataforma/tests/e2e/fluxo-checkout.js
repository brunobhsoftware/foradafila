// Checkout pago (V012/V013): compra na página do evento → reserva → order no Mercado Pago (falso, porta 8099) com a
// conta da plataforma → retorno pendente → webhook aprova → ingressos emitidos; recusa libera a reserva; reserva vencida
// volta ao estoque. Laravel na 8001 com as variáveis MP_* de teste (ver fluxo-financeiro.js):
//   BASE=http://127.0.0.1:8001 node tests/e2e/fluxo-checkout.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const http = require('http');
const crypto = require('crypto');
const BASE = process.env.BASE || 'http://127.0.0.1:8001';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

let n = 0; const orders = {}; let ultimoCorpo = null; const reembolsos = [];
const falso = http.createServer((req, res) => {
  let corpo = ''; req.on('data', (d) => { corpo += d; }); req.on('end', () => {
    const json = (s, o) => { res.writeHead(s, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(o)); };
    if (req.url.startsWith('/oauth/token')) return json(200, { access_token: 'APP_USR-bh-cc', expires_in: 21600 });
    if (req.url === '/v1/orders' && req.method === 'POST') {
      const id = 'ORD' + (++n); ultimoCorpo = JSON.parse(corpo); orders[id] = { status: 'action_required', status_detail: 'waiting_payment' };
      return json(201, { id, status: 'action_required', checkout_url: 'http://127.0.0.1:8099/pagar/' + id });
    }
    const rf = req.url.match(/^\/v1\/orders\/(ORD\d+)\/refund/);
    if (rf && req.method === 'POST') { reembolsos.push({ id: rf[1], corpo: corpo ? JSON.parse(corpo) : null, token: req.headers.authorization }); return json(201, { id: rf[1], status: 'refunded' }); }
    const m = req.url.match(/^\/v1\/orders\/(ORD\d+)/);
    if (m && orders[m[1]]) return json(200, { id: m[1], ...orders[m[1]], transactions: { payments: [{ id: 'PAY' + m[1], payment_method: { type: 'bank_transfer', installments: 1 } }] } });
    if (req.url.startsWith('/pagar/')) { res.writeHead(200, { 'Content-Type': 'text/html' }); return res.end('<h1>Checkout falso do Mercado Pago</h1>'); }
    json(404, {});
  });
}).listen(8099);

const webhook = async (P, id) => {
  const ts = Math.floor(Date.now() / 1000); const reqId = 'req-' + id + '-' + ts;
  const v1 = crypto.createHmac('sha256', 'wh-teste').update(`id:${id.toLowerCase()};request-id:${reqId};ts:${ts};`).digest('hex');
  return P.request.post(BASE + '/webhooks/mercadopago?data.id=' + id + '&type=order', { headers: { 'x-signature': `ts=${ts},v1=${v1}`, 'x-request-id': reqId, 'content-type': 'application/json' }, data: { id: 'evt-' + reqId, type: 'order', action: 'order.processed', data: { id } } });
};

(async () => {
  sql("UPDATE taxa_regra SET percentual=5, valor_fixo=0, valor_minimo=1 WHERE operacao='ingresso_pago'");
  const b = await chromium.launch(); const erros = [];
  const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage(); P.on('pageerror', (e) => erros.push(e.message));
  await P.goto(BASE + '/cadastro');
  for (const [n2, v] of [['nome', 'Bruno Checkout'], ['email', 'bruno@checkout.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n2}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));
  sql("UPDATE webhook_evento SET id=id"); // noop
  // Organização: repassa a taxa; Financeiro: Pix
  sql("UPDATE organizador SET taxa_comprador=1");
  await P.goto(BASE + '/painel/financeiro');
  await P.selectOption('[name=pix_tipo]', 'email'); await P.fill('[name=pix_chave]', 'pix@slod.test');
  await nav(P, P.click('button:has-text("Salvar dados de recebimento")'));
  // evento com lote pago de R$ 100 (10 ingressos)
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Show Pago');
  await P.fill('[name=data_inicio]', local(15)); await P.fill('[name=data_fim]', local(16, '02:00'));
  await P.fill('[name="local[nome]"]', 'Local'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  const f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('[name=nome]').fill('Pista'); await f.locator('[name=quantidade]').fill('10');
  await f.locator('input[name=cobranca][value=pago]').check({ force: true }); await f.locator('[name=preco]').fill('100');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  await nav(P, P.click('button:has-text("Publicar evento")'));

  // ---- compra ----
  const C = await (await b.newContext()).newPage(); C.on('pageerror', (e) => erros.push(e.message));
  await C.goto(BASE + '/e/show-pago');
  ok(await C.locator('button:has-text("Comprar ingresso")').count() === 1 && !(await C.content()).includes('venda online em breve'), 'página do evento vende o lote pago');
  await C.selectOption('select[name^="q["]', '2');
  await nav(C, C.click('button:has-text("Comprar ingresso")'));
  let t = await C.content();
  ok(t.includes('Comprar ingresso') && t.includes('R$ 210,00') && t.includes('R$ 100,00 + R$ 5,00 de taxa'), 'resumo com taxa repassada: 2 × R$ 105 = R$ 210');
  for (const [k, v] of [['nome', 'Ana Compradora'], ['email', 'ana@compra.test'], ['senha', 'Ingresso1'], ['senha_confirmation', 'Ingresso1']]) await C.fill(`[name=${k}]`, v);
  await C.fill('[name="participantes[0][nome]"]', 'Ana Compradora'); await C.fill('[name="participantes[0][cpf]"]', '529.982.247-25');
  await C.fill('[name="participantes[1][nome]"]', 'Pedro Amigo');
  for (const k of ['compra_comprador', 'privacidade']) await C.check(`[name="aceites[${k}]"]`);
  await nav(C, C.click('button:has-text("Ir para o pagamento")'));
  ok(C.url() === 'http://127.0.0.1:8099/pagar/ORD1', 'vai para o checkout do Mercado Pago');
  ok(sql("SELECT CONCAT_WS('|', status, valor_bruto, valor_taxa_servico, valor_total) FROM pedido") === 'aguardando_pagamento|200.00|10.00|210.00', 'pedido aguardando pagamento (R$ 200 + R$ 10 de taxa)');
  ok(sql("SELECT CONCAT_WS('|', reservado, vendido) FROM lote") === '2|0', 'estoque reservado (2), nada vendido');
  ok(sql("SELECT CONCAT_WS('|', status, gateway_pedido_id, valor, comissao_retida, valor_liquido_org) FROM pagamento") === 'criado|ORD1|210.00|10.00|200.00', 'pagamento criado com a comissão de R$ 10 e R$ 200 do produtor');
  ok(ultimoCorpo.total_amount === '210.00' && ultimoCorpo.items[0].unit_price === '105.00' && ultimoCorpo.items[0].quantity === 2 && ultimoCorpo.external_reference.length > 4
    && ultimoCorpo.payer.identification.number === '52998224725' && ultimoCorpo.config.statement_descriptor === 'FORADAFILA' && ultimoCorpo.config.online.transaction_security.liability_shift === 'required', 'order completa (itens, comprador com CPF, descrição na fatura, 3DS)');
  ok(sql('SELECT COUNT(*) FROM ingresso') === '0', 'nenhum ingresso antes do pagamento');

  // volta do Mercado Pago ainda pendente
  const uuid = sql('SELECT uuid FROM pedido');
  await C.goto(BASE + '/pedido/' + uuid + '/pagamento?resultado=pendente');
  ok((await C.content()).includes('Aguardando o pagamento'), 'retorno pendente: aguardando');
  await C.goto(BASE + '/minha-conta');
  ok((await C.content()).includes('Pagar ou conferir pagamento'), 'Meus ingressos mostra o pedido aguardando com botão de pagar');

  // webhook: aprovado
  orders.ORD1 = { status: 'processed', status_detail: 'accredited' };
  const r = await webhook(P, 'ORD1');
  ok(r.status() === 200 && sql("SELECT status FROM pedido") === 'pago', 'webhook aprova o pedido');
  ok(sql("SELECT CONCAT_WS('|', reservado, vendido) FROM lote") === '0|2', 'reserva vira venda');
  ok(sql("SELECT GROUP_CONCAT(CONCAT(p.nome,'=',i.valor_pago) ORDER BY i.numero) FROM ingresso i JOIN participante p ON p.ingresso_id=i.id") === 'Ana Compradora=105.00,Pedro Amigo=105.00', 'ingressos emitidos com os participantes e o valor pago');
  ok(sql("SELECT CONCAT_WS('|', status, metodo) FROM pagamento") === 'aprovado|pix' && sql("SELECT status FROM webhook_evento ORDER BY id DESC LIMIT 1") === 'processado', 'pagamento aprovado e webhook processado');
  ok(sql("SELECT COUNT(*) FROM mensagem_envio WHERE destinatario='ana@compra.test' AND tipo='confirmacao_compra'") === '1', 'e-mail com os ingressos');
  await webhook(P, 'ORD1');
  ok(sql('SELECT COUNT(*) FROM ingresso') === '2', 'webhook repetido não emite de novo');
  await C.goto(BASE + '/pedido/' + uuid + '/pagamento?resultado=sucesso');
  ok(C.url().endsWith('/minha-conta') && (await C.content()).includes('Pagamento aprovado'), 'retorno aprovado leva a Meus ingressos');

  // ---- pagamento recusado libera a reserva ----
  await C.goto(BASE + '/e/show-pago');
  await C.selectOption('select[name^="q["]', '1');
  await nav(C, C.click('button:has-text("Comprar ingresso")'));
  await C.fill('[name="participantes[0][nome]"]', 'Ana Compradora');
  for (const k of ['compra_comprador', 'privacidade']) await C.check(`[name="aceites[${k}]"]`);
  await nav(C, C.click('button:has-text("Ir para o pagamento")'));
  ok(sql("SELECT reservado FROM lote") === '1', 'segunda compra reserva 1');
  orders.ORD2 = { status: 'failed', status_detail: 'rejected_by_issuer' };
  const uuid2 = sql("SELECT uuid FROM pedido ORDER BY id DESC LIMIT 1");
  await C.goto(BASE + '/pedido/' + uuid2 + '/pagamento?resultado=falha');
  ok((await C.content()).includes('Pagamento não concluído') && sql("SELECT reservado FROM lote") === '0'
    && sql("SELECT status FROM pedido ORDER BY id DESC LIMIT 1") === 'recusado', 'recusado: reserva devolvida ao estoque');

  // ---- reserva vencida volta ao estoque ----
  await C.goto(BASE + '/e/show-pago');
  await C.selectOption('select[name^="q["]', '3');
  await nav(C, C.click('button:has-text("Comprar ingresso")'));
  for (let i = 0; i < 3; i++) await C.fill(`[name="participantes[${i}][nome]"]`, 'P' + i);
  for (const k of ['compra_comprador', 'privacidade']) await C.check(`[name="aceites[${k}]"]`);
  await nav(C, C.click('button:has-text("Ir para o pagamento")'));
  sql("UPDATE pedido SET expira_em = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE status='aguardando_pagamento'");
  await C.goto(BASE + '/e/show-pago');
  ok(sql("SELECT reservado FROM lote") === '0' && sql("SELECT status FROM pedido ORDER BY id DESC LIMIT 1") === 'expirado', 'reserva vencida e não paga volta ao estoque ao abrir o evento');

  // ---- extrato e repasse ----
  await P.goto(BASE + '/painel/financeiro');
  t = (await P.locator('section:has(h2:text("Extrato do recebimento"))').innerText()).replace(/\s+/g, ' ');
  ok(t.includes('Vendas pagas a repassar R$ 210,00') && t.includes('Taxa da plataforma R$ 10,00') && t.includes('Aguardando o fim do evento R$ 200,00'), 'extrato da produtora: R$ 210 de vendas, R$ 10 de taxa, R$ 200 aguardando o evento');
  sql("UPDATE evento SET data_inicio = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 11 DAY), data_fim = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 DAY)");
  sql("UPDATE usuario SET admin_plataforma=1 WHERE email='bruno@checkout.test'");
  await P.goto(BASE + '/admin/repasses');
  t = (await P.locator('table.tabela').first().innerText()).replace(/\s+/g, ' ');
  ok(t.includes('Estação Slod') && t.includes('Pix E-mail: pix@slod.test') && t.includes('R$ 200,00'), 'Administração: R$ 200 liberados para a Estação Slod no Pix cadastrado');
  P.on('dialog', (d) => d.accept());
  await P.click('button:has-text("Registrar repasse")');
  await P.fill('tr.linha-acoes:visible [name=comprovante]', 'E2E123456');
  await nav(P, P.click('tr.linha-acoes:visible button:has-text("Registrar repasse de")'));
  ok((await P.content()).includes('Repasse de R$ 200,00 para Estação Slod registrado') && sql("SELECT CONCAT_WS('|', valor_vendas, valor_comissao, valor_repasse, comprovante) FROM repasse") === '210.00|10.00|200.00|E2E123456', 'repasse registrado (R$ 210 − R$ 10 = R$ 200)');
  ok(sql("SELECT COUNT(*) FROM pagamento WHERE repasse_id IS NOT NULL") === '1' && (await P.content()).includes('Nada a repassar'), 'pagamento fechado no repasse; nada mais a repassar');
  await P.goto(BASE + '/painel/financeiro');
  ok((await P.content()).includes('E2E123456'), 'produtora vê o repasse recebido');

  // ---- cancelar ingresso pago devolve pelo Mercado Pago (parcial: 1 de 2) ----
  const ev = sql("SELECT uuid FROM evento");
  await P.goto(BASE + '/painel/participantes?evento=' + ev);
  await P.locator('tr:not(.linha-acoes):has-text("Pedro Amigo") button:has-text("Ações")').click();
  await nav(P, P.locator('tr.linha-acoes:visible button:has-text("Cancelar ingresso")').click());
  ok(reembolsos.length === 1 && reembolsos[0].id === 'ORD1' && reembolsos[0].corpo.transactions[0].amount === '105.00' && reembolsos[0].corpo.transactions[0].id === 'PAYORD1',
    'cancelar 1 ingresso devolve R$ 105 pelo Mercado Pago (devolução parcial da order)');
  ok(sql("SELECT status FROM pagamento WHERE gateway_pedido_id='ORD1'") === 'estorno_parcial' && sql("SELECT status FROM reembolso") === 'concluido', 'pagamento com estorno parcial e devolução concluída');
  await P.goto(BASE + '/painel/eventos');
  t = (await P.locator('.evento-painel').first().innerText()).replace(/\s+/g, ' ');
  ok(t.includes('Valor vendido R$ 210,00') && t.includes('Taxa da plataforma R$ 5,00') && t.includes('Devolvido R$ 105,00') && t.includes('Valor líquido R$ 100,00')
    && (await P.locator('.evento-painel [data-compartilhar]').count()) === 1, 'Meus eventos: vendido, taxa dos válidos, devolvido, líquido e botão de compartilhar');
  const linkCodigo = await P.inputValue('.evento-painel input[id^=link-ev-]');
  ok(/\/e\/[0-9a-f]{10}$/.test(linkCodigo), 'link para compartilhar usa código fixo: ' + linkCodigo);
  sql("UPDATE evento SET titulo='Show Renomeado', slug='show-renomeado'");
  await C.goto(linkCodigo);
  ok((await C.content()).includes('Show Renomeado'), 'link com código continua abrindo o evento depois de mudar o nome');
  // ---- homologação por empresa ----
  await P.goto(BASE + '/painel/financeiro');
  await P.check('input[name=ambiente_vendas][value=homologacao]', { force: true });
  await nav(P, P.click('button:has-text("Salvar ambiente")'));
  ok((await P.content()).includes('Vendas em HOMOLOGAÇÃO') && sql("SELECT valor FROM parametro_sistema WHERE chave LIKE 'mp_vendas_teste_org_%'") === '1', 'empresa coloca as vendas em homologação');
  sql("UPDATE evento SET data_inicio = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 DAY), data_fim = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 6 DAY)");
  sql("UPDATE lote SET venda_fim = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 6 DAY)");
  await C.goto(linkCodigo);
  ok((await C.content()).includes('Ambiente de teste (homologação)'), 'página do evento avisa que é teste');

  sql("UPDATE taxa_regra SET percentual=0, valor_fixo=0, valor_minimo=0 WHERE operacao='ingresso_pago'");
  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close(); falso.close();
})();
