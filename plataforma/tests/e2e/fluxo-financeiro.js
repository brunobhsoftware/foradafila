// Teste de ponta a ponta da conexão com o Mercado Pago (OAuth) e dos avisos (webhooks), com um
// Mercado Pago FALSO local. Suba o Laravel com as variáveis do falso, por exemplo:
//   MP_CLIENT_ID=app-teste MP_CLIENT_SECRET=segredo-teste MP_WEBHOOK_SECRET=wh-teste \
//   MP_API_URL=http://127.0.0.1:8099 MP_AUTORIZACAO_URL=http://127.0.0.1:8099/authorization \
//   php artisan serve --port=8001
//   BASE=http://127.0.0.1:8001 node tests/e2e/fluxo-financeiro.js
const { chromium } = require('playwright');
const http = require('http');
const crypto = require('crypto');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8001';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

// ---- Mercado Pago falso ----
let ultimoToken = null; let recusar = false; let ultimaAutorizacao = null;
const falso = http.createServer((req, res) => {
  const u = new URL(req.url, 'http://x');
  if (u.pathname === '/authorization') {
    ultimaAutorizacao = u.searchParams;
    const volta = new URL(u.searchParams.get('redirect_uri'));
    volta.searchParams.set('state', u.searchParams.get('state'));
    if (u.searchParams.get('client_id') === 'app-teste') volta.searchParams.set('code', 'TG-codigo-123');
    else volta.searchParams.set('error', 'invalid_request');
    res.writeHead(302, { Location: volta.toString() }); return res.end();
  }
  if (u.pathname === '/oauth/token') {
    let corpo = ''; req.on('data', (d) => { corpo += d; }); req.on('end', () => {
      ultimoToken = Object.fromEntries(new URLSearchParams(corpo));
      if (recusar) { res.writeHead(400, { 'Content-Type': 'application/json' }); return res.end(JSON.stringify({ message: 'invalid_grant' })); }
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ access_token: 'APP_USR-token-secreto', token_type: 'Bearer', expires_in: 15552000, scope: 'offline_access read write',
        user_id: 987654321, refresh_token: 'TG-refresh-secreto', public_key: 'APP_USR-chave-publica', live_mode: false }));
    }); return;
  }
  if (u.pathname === '/users/me') {
    res.writeHead(req.headers.authorization === 'Bearer APP_USR-token-secreto' ? 200 : 401, { 'Content-Type': 'application/json' });
    return res.end(JSON.stringify({ id: 987654321, email: 'vendas@slod.test', nickname: 'ESTACAOSLOD', site_id: 'MLB' }));
  }
  res.writeHead(404); res.end();
}).listen(8099);

(async () => {
  sql("REPLACE INTO parametro_sistema (chave, valor) VALUES ('mp_pkce', '1')"); // PKCE ligado na primeira parte
  const b = await chromium.launch(); const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const erros = []; P.on('pageerror', (e) => erros.push(e.message));
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Fin'], ['email', 'bruno@fin.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));

  await nav(P, P.click('.menu-lateral a:has-text("Financeiro")'));
  ok(P.url().endsWith('/painel/financeiro') && (await P.content()).includes('Não conectada'), 'menu abre Financeiro com a conta não conectada');

  // conexão recusada pelo Mercado Pago
  recusar = true;
  await nav(P, P.click('button:has-text("Conectar Mercado Pago")'));
  ok((await P.content()).includes('O Mercado Pago recusou a autorização (HTTP 400: invalid_grant)'), 'recusa do Mercado Pago vira mensagem na tela');
  ok(sql("SELECT COUNT(*) FROM conta_pagamento WHERE modo='direta'") === '0', 'nada gravado quando recusa');

  // conexão aceita
  recusar = false;
  await nav(P, P.click('button:has-text("Conectar Mercado Pago")'));
  const tela = await P.content();
  ok(tela.includes('Conta do Mercado Pago conectada (vendas@slod.test)') && tela.includes('ESTACAOSLOD') && tela.includes('Testes (sandbox)'), 'conta conectada com e-mail, apelido e ambiente');
  ok(ultimoToken.grant_type === 'authorization_code' && ultimoToken.code === 'TG-codigo-123' && ultimoToken.client_secret === 'segredo-teste'
    && ultimoToken.code_verifier && ultimoToken.redirect_uri.endsWith('/painel/financeiro/mercadopago/retorno'), 'troca o código com PKCE e o endereço de retorno');
  const linha = sql("SELECT CONCAT_WS('|', status, ambiente, conta_externa_id, email_conta, pais_conta, escopos, public_key) FROM conta_pagamento WHERE modo='direta'");
  ok(linha === 'conectada|sandbox|987654321|vendas@slod.test|MLB|offline_access read write|APP_USR-chave-publica', 'dados gravados: ' + linha);
  ok(sql("SELECT access_token_cript LIKE '%token-secreto%' OR refresh_token_cript LIKE '%refresh-secreto%' FROM conta_pagamento WHERE modo='direta'") === '0', 'tokens gravados criptografados');
  ok(!tela.includes('token-secreto'), 'token não aparece na tela');
  ok(sql("SELECT COUNT(*) FROM auditoria WHERE acao='conta_pagamento.conectar' AND depois NOT LIKE '%token_cript%'") === '1', 'auditoria sem os tokens');

  // retorno forjado (state errado) é recusado
  await P.goto(BASE + '/painel/financeiro/mercadopago/retorno?code=x&state=forjado');
  ok((await P.content()).includes('Autorização inválida ou expirada'), 'retorno com state inválido é recusado');

  // desconectar
  P.once('dialog', (d) => d.accept());
  await nav(P, P.click('button:has-text("Desconectar")'));
  ok(sql("SELECT CONCAT(status, '|', access_token_cript IS NULL) FROM conta_pagamento WHERE modo='direta'") === 'revogada|1' && (await P.content()).includes('Não conectada'), 'desconectar apaga os tokens');

  // PKCE desligado (padrão): não envia code_challenge nem code_verifier
  sql("UPDATE parametro_sistema SET valor='0' WHERE chave='mp_pkce'");
  await nav(P, P.click('button:has-text("Conectar Mercado Pago")'));
  ok(!ultimaAutorizacao.has('code_challenge') && !ultimaAutorizacao.has('code_challenge_method') && ultimoToken.code && !('code_verifier' in ultimoToken),
    'PKCE desligado: autorização e troca sem os campos do PKCE');
  ok((await P.content()).includes('Conta do Mercado Pago conectada'), 'conecta também sem PKCE');
  sql("DELETE FROM parametro_sistema WHERE chave='mp_pkce'");

  // ---- webhooks ----
  const enviar = async (corpo, assinar, rid = 'req-' + Math.random()) => {
    const ts = String(Date.now());
    const v1 = crypto.createHmac('sha256', 'wh-teste').update(`id:${corpo.data.id};request-id:${rid};ts:${ts};`).digest('hex');
    const r = await P.request.post(BASE + '/webhooks/mercadopago?data.id=' + corpo.data.id + '&type=payment', {
      data: corpo, headers: { 'x-request-id': rid, 'x-signature': `ts=${ts},v1=${assinar ? v1 : 'f'.repeat(64)}` } });
    return r.status();
  };
  ok(await enviar({ id: 111, type: 'payment', action: 'payment.updated', data: { id: '555' } }, true) === 200, 'aviso assinado responde 200 (sem CSRF)');
  ok(sql("SELECT CONCAT_WS('|', assinatura_valida, status, acao, recurso_id) FROM webhook_evento WHERE id_evento_externo='111'") === '1|recebido|payment.updated|555', 'aviso válido gravado como recebido');
  ok(await enviar({ id: 222, type: 'payment', action: 'payment.updated', data: { id: '556' } }, false) === 200, 'aviso com assinatura falsa também responde 200');
  ok(sql("SELECT CONCAT(assinatura_valida, '|', status) FROM webhook_evento WHERE id_evento_externo='222'") === '0|ignorado', 'assinatura falsa: gravado e ignorado');
  await enviar({ id: 111, type: 'payment', action: 'payment.updated', data: { id: '555' } }, true);
  ok(sql("SELECT COUNT(*) FROM webhook_evento WHERE id_evento_externo='111'") === '1', 'aviso repetido não duplica');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close(); falso.close();
})();
