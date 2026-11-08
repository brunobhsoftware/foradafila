// Teste da aplicação Mercado Pago PRÓPRIA da empresa (V012): chaves de teste/produção no Financeiro (criptografadas,
// em branco mantém), verificação do token (/users/me falso), ativação, escolha "direto na conta da empresa", publicação
// de lote pago ligada a essa conta e webhook por empresa com a assinatura dela. Laravel na 8001 com MP_API_URL=http://127.0.0.1:8099:
//   BASE=http://127.0.0.1:8001 node tests/e2e/fluxo-mp-propria.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const http = require('http');
const crypto = require('crypto');
const BASE = process.env.BASE || 'http://127.0.0.1:8001';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

const falso = http.createServer((req, res) => {
  const tok = (req.headers.authorization || '').replace('Bearer ', '');
  if (req.url.startsWith('/users/me')) {
    if (tok === 'APP_USR-prod-slod') { res.writeHead(200, { 'Content-Type': 'application/json' }); return res.end(JSON.stringify({ id: 777, email: 'estacaoslod@gmail.com', nickname: 'ESTACAOSLOD', tags: ['normal'] })); }
    if (tok === 'APP_USR-teste-slod') { res.writeHead(200, { 'Content-Type': 'application/json' }); return res.end(JSON.stringify({ id: 888, email: 'test_user_1@testuser.com', nickname: 'TESTUSER1', tags: ['test_user'] })); }
    res.writeHead(401); return res.end('{}');
  }
  if (req.url === '/v1/orders' && req.method === 'POST') {
    let corpo = ''; req.on('data', (d) => { corpo += d; }); req.on('end', () => {
      ordem = { token: tok, corpo: JSON.parse(corpo) };
      res.writeHead(201, { 'Content-Type': 'application/json' }); res.end(JSON.stringify({ id: 'ORDSLOD1', status: 'action_required', checkout_url: 'http://127.0.0.1:8099/pagar/ORDSLOD1' }));
    }); return;
  }
  if (req.url.startsWith('/v1/orders/ORDSLOD1')) { res.writeHead(200, { 'Content-Type': 'application/json' }); return res.end(JSON.stringify({ id: 'ORDSLOD1', status: 'processed', status_detail: 'accredited', total_amount: '5.00', transactions: { payments: [{ id: 'PAYSLOD1' }] } })); }
  res.writeHead(404); res.end('{}');
}).listen(8099);
let ordem = null;

(async () => {
  const b = await chromium.launch(); const erros = [];
  const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage(); P.on('pageerror', (e) => erros.push(e.message));
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Propria'], ['email', 'bruno@propria.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));

  await P.goto(BASE + '/painel/financeiro');
  ok(await P.isDisabled('input[name=recebimento_modo][value=propria]'), 'sem aplicação ativa: "direto na conta da empresa" desativado');
  await P.click('summary:has-text("Mercado Pago da empresa")');
  const prod = P.locator('form:has(button:has-text("Salvar chaves de produção"))');
  const teste = P.locator('form:has(button:has-text("Salvar chaves de teste"))');
  await prod.locator('[name=access_token]').fill('token-errado');
  await nav(P, prod.locator('button:has-text("Salvar")').click());
  ok((await P.content()).includes('começa com APP_USR-'), 'Access Token em formato errado é recusado');
  await P.locator('form:has(button:has-text("Salvar chaves de teste")) [name=client_id]').fill('1111');
  await P.locator('form:has(button:has-text("Salvar chaves de teste")) [name=access_token]').fill('APP_USR-teste-slod');
  await nav(P, P.locator('form:has(button:has-text("Salvar chaves de teste")) button:has-text("Salvar")').click());
  const pr = P.locator('form:has(button:has-text("Salvar chaves de produção"))');
  await pr.locator('[name=client_id]').fill('2222'); await pr.locator('[name=client_secret]').fill('segredo-slod');
  await pr.locator('[name=access_token]').fill('APP_USR-prod-slod'); await pr.locator('[name=webhook_secret]').fill('wh-slod');
  await nav(P, pr.locator('button:has-text("Salvar")').click());
  ok((await P.content()).includes('Chaves de produção salvas') && sql("SELECT CONCAT_WS('|', modo, client_id, teste_client_id, status) FROM conta_pagamento WHERE modo='propria'") === 'propria|2222|1111|em_homologacao', 'chaves salvas (produção aguardando verificação)');
  ok(sql("SELECT access_token_cript NOT LIKE '%APP_USR%' AND client_secret_cript NOT LIKE '%segredo%' FROM conta_pagamento WHERE modo='propria'") === '1', 'segredos gravados criptografados');
  // em branco mantém
  await nav(P, P.locator('form:has(button:has-text("Salvar chaves de produção")) button:has-text("Salvar")').click());
  ok(sql("SELECT access_token_cript IS NOT NULL FROM conta_pagamento WHERE modo='propria'") === '1', 'salvar em branco mantém o token');
  const webhook = await P.inputValue('#mp-prop-webhook');
  ok((await P.inputValue('#mp-prop-site')).startsWith('http'), 'mostra o site para cadastrar na aplicação');
  ok(/\/webhooks\/mercadopago\/[0-9a-f-]{36}$/.test(webhook), 'mostra o endereço de webhooks da empresa: ' + webhook);

  await nav(P, P.locator('button:has-text("Verificar de quem é o token")').last().click());
  let t = await P.content();
  ok(t.includes('estacaoslod@gmail.com (ESTACAOSLOD) — conta real') && t.includes('test_user_1@testuser.com (TESTUSER1) — conta de teste'), 'verificação mostra a conta de cada token');
  ok(sql("SELECT CONCAT_WS('|', status, conta_externa_id) FROM conta_pagamento WHERE modo='propria'") === 'ativa|777', 'token de produção válido ativa a aplicação própria');

  // pagamento de teste (homologação) com o token de teste da empresa
  const ft = P.locator('form:has(button:has-text("Pagar teste no Mercado Pago"))');
  await ft.locator('[name=email]').fill('test_user_comprador@testuser.com');
  await nav(P, ft.locator('button').click());
  ok(ordem && ordem.token === 'APP_USR-teste-slod' && ordem.corpo.total_amount === '5.00' && ordem.corpo.payer.first_name === 'APRO'
    && (await P.inputValue('#link-teste')) === 'http://127.0.0.1:8099/pagar/ORDSLOD1', 'pagamento de teste usa o token de TESTE da empresa e mostra o link');
  const R = await (await b.newContext()).newPage();
  await R.goto(ordem.corpo.config.online.success_url);
  ok((await R.content()).includes('ORDSLOD1') && (await R.content()).includes('Aprovado'), 'retorno do teste (sem login) mostra a order aprovada');
  await P.goto(BASE + '/painel/financeiro?mp=1');
  const fp = P.locator('form:has(button:has-text("Pagar de verdade no Mercado Pago"))');
  await fp.locator('[name=cpf]').fill('529.982.247-25'); await fp.locator('[name=nome]').fill('Maria Silva'); await fp.locator('[name=email]').fill('maria@real.test');
  await nav(P, fp.locator('button').click());
  ok(ordem.token === 'APP_USR-prod-slod', 'pagamento de produção usa o token de PRODUÇÃO da empresa');
  await P.goto(BASE + '/painel/financeiro');

  // escolher receber direto
  await P.check('input[name=recebimento_modo][value=propria]', { force: true });
  await nav(P, P.click('button:has-text("Salvar forma de recebimento")'));
  ok((await P.content()).includes('direto na conta do Mercado Pago da empresa') && sql('SELECT recebimento_modo FROM organizador') === 'propria', 'forma de recebimento: direto na conta da empresa');

  // evento pago publica com a conta própria
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Show Direto');
  await P.fill('[name=data_inicio]', local(15)); await P.fill('[name=data_fim]', local(16, '02:00'));
  await P.fill('[name="local[nome]"]', 'Local'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  const f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('[name=nome]').fill('Pista'); await f.locator('[name=quantidade]').fill('50');
  await f.locator('input[name=cobranca][value=pago]').check({ force: true }); await f.locator('[name=preco]').fill('80');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  await nav(P, P.click('button:has-text("Publicar evento")'));
  ok(sql("SELECT CONCAT_WS('|', e.status, c.modo) FROM evento e JOIN conta_pagamento c ON c.id=e.conta_pagamento_id") === 'publicado|propria', 'evento pago publicado na conta própria da empresa');

  // webhook da empresa com a assinatura dela
  const ts = Math.floor(Date.now() / 1000); const reqId = 'req-slod-1';
  const v1 = crypto.createHmac('sha256', 'wh-slod').update(`id:pay123;request-id:${reqId};ts:${ts};`).digest('hex');
  const r = await P.request.post(webhook + '?data.id=pay123&type=payment', { headers: { 'x-signature': `ts=${ts},v1=${v1}`, 'x-request-id': reqId, 'content-type': 'application/json' }, data: { id: 'evt-slod-1', type: 'payment', data: { id: 'pay123' } } });
  ok(r.status() === 200 && sql("SELECT CONCAT_WS('|', assinatura_valida, status, conta_pagamento_id IS NOT NULL) FROM webhook_evento WHERE id_evento_externo='evt-slod-1'") === '1|recebido|1', 'webhook da empresa: assinatura dela confere e fica ligado à conta');
  const r2 = await P.request.post(webhook, { headers: { 'x-signature': `ts=${ts},v1=errado`, 'x-request-id': 'x2' }, data: { id: 'evt-slod-2', data: { id: 'pay124' } } });
  ok(r2.status() === 200 && sql("SELECT status FROM webhook_evento WHERE id_evento_externo='evt-slod-2'") === 'ignorado', 'assinatura errada é ignorada');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close(); falso.close();
})();
