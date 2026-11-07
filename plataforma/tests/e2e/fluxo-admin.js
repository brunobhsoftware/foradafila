// Teste de ponta a ponta da Administração da plataforma: acesso só para admin_plataforma e
// cadastro das chaves do Mercado Pago (guardadas no banco, segredos criptografados).
// Rode contra o Laravel do fluxo-financeiro (porta 8001, Mercado Pago falso na 8099):
//   BASE=http://127.0.0.1:8001 node tests/e2e/fluxo-admin.js
const { chromium } = require('playwright');
const http = require('http');
const crypto = require('crypto');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8001';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

let clientIdUsado = null; let segredoUsado = null; let preferencia = null; let autorizacaoPref = null;
const falso = http.createServer((req, res) => {
  const u = new URL(req.url, 'http://x');
  if (u.pathname === '/authorization') { clientIdUsado = u.searchParams.get('client_id'); res.writeHead(302, { Location: u.searchParams.get('redirect_uri') + '?error=cancelado&state=' + u.searchParams.get('state') }); return res.end(); }
  if (u.pathname === '/users/me') {
    const a = req.headers.authorization; res.writeHead(200, { 'Content-Type': 'application/json' });
    return res.end(JSON.stringify(a === 'Bearer APP_USR-teste-123' ? { id: 2001, nickname: 'TESTUSER123', email: 'test_user_1@testuser.com', tags: ['test_user'] } : { id: 1001, nickname: 'BHSOFTWARE', email: 'bh@bh.test', tags: ['normal'] }));
  }
  if (u.pathname === '/v1/payments/123456') {
    res.writeHead(['Bearer APP-TOKEN-BH', 'Bearer APP_USR-teste-123', 'Bearer APP_USR-teste-cc'].includes(req.headers.authorization) ? 200 : 401, { 'Content-Type': 'application/json' });
    return res.end(JSON.stringify({ id: 123456, status: 'approved', status_detail: 'accredited', transaction_amount: 5, payment_method_id: 'pix', payment_type_id: 'bank_transfer' }));
  }
  let corpo = ''; req.on('data', (d) => { corpo += d; }); req.on('end', () => {
    if (u.pathname === '/checkout/preferences') {
      preferencia = JSON.parse(corpo); autorizacaoPref = req.headers.authorization;
      const volta = preferencia.back_urls.success + '?collection_id=123456&payment_id=123456&status=approved&merchant_order_id=987654&preference_id=PREF-1&external_reference=' + preferencia.external_reference;
      res.writeHead(201, { 'Content-Type': 'application/json' }); return res.end(JSON.stringify({ id: 'PREF-1', init_point: volta }));
    }
    const f = new URLSearchParams(corpo); segredoUsado = f.get('client_secret');
    if (f.get('grant_type') === 'client_credentials' && f.get('client_id') === 'teste-id') { res.writeHead(200, { 'Content-Type': 'application/json' }); return res.end(JSON.stringify({ access_token: 'APP_USR-teste-cc', expires_in: 21600 })); }
    if (f.get('grant_type') === 'client_credentials') { res.writeHead(200, { 'Content-Type': 'application/json' }); return res.end(JSON.stringify({ access_token: 'APP-TOKEN-BH', expires_in: 21600 })); }
    res.writeHead(400); res.end('{}');
  });
}).listen(8099);

(async () => {
  sql("DELETE FROM parametro_sistema WHERE chave LIKE 'mp\\_%'");
  const b = await chromium.launch(); const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const erros = []; P.on('pageerror', (e) => erros.push(e.message));

  // link no rodapé; visitante vai para o login
  await P.goto(BASE + '/');
  ok(await P.locator('footer a:has-text("Administração")').count() === 1, 'rodapé tem o link Administração');
  await nav(P, P.click('footer a:has-text("Administração")'));
  ok(P.url().endsWith('/entrar'), 'sem login, vai para a tela de entrar');

  // produtor comum: 404
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Admin'], ['email', 'bruno@admin.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));
  ok((await P.goto(BASE + '/admin')).status() === 404, 'produtor sem permissão recebe 404');

  // vira administrador
  sql("UPDATE usuario SET admin_plataforma=1 WHERE email='bruno@admin.test'");
  await P.goto(BASE + '/admin');
  ok((await P.content()).includes('Administração da plataforma') && (await P.content()).includes('Configurar Mercado Pago'), 'administrador entra na Administração');
  await nav(P, P.click('a:has-text("Configurar Mercado Pago")'));
  const prompt = await P.inputValue('#adm-prompt');
  ok(prompt.includes('MP_CLIENT_ID=...') && prompt.includes('MP_WEBHOOK_SECRET=...') && prompt.includes('/painel/financeiro/mercadopago/retorno') && prompt.includes('/webhooks/mercadopago'),
    'prompt de IA pede os três valores e traz os endereços');
  ok(await P.getAttribute('a:has-text("painel de desenvolvedores")', 'href') === 'https://www.mercadopago.com.br/developers/panel/app', 'botão do painel de desenvolvedores');

  await nav(P, P.click('button:has-text("Salvar chaves de produção")'));
  ok((await P.content()).includes('Preencha pelo menos um campo'), 'tudo em branco é recusado');
  await P.fill('[name=client_id]', '1234567890123456'); await P.fill('[name=client_secret]', 'SegredoDaBH-abcdWXYZ'); await P.fill('[name=webhook_secret]', 'assinatura-wh-9876');
  await nav(P, P.click('button:has-text("Salvar chaves de produção")'));
  const tela = await P.content();
  ok(tela.includes('Chaves do Mercado Pago salvas') && tela.includes('Configurada'), 'chaves salvas');
  ok(sql("SELECT valor FROM parametro_sistema WHERE chave='mp_client_id'") === '1234567890123456', 'Client ID gravado');
  ok(sql("SELECT COUNT(*) FROM parametro_sistema WHERE chave IN ('mp_client_secret','mp_webhook_secret') AND valor NOT LIKE '%Segredo%' AND valor NOT LIKE '%assinatura%'") === '2', 'segredos criptografados no banco');
  ok(!tela.includes('SegredoDaBH-abcdWXYZ') && tela.includes('••••WXYZ'), 'segredo não volta para a tela (só os 4 últimos)');
  ok(sql("SELECT COUNT(*) FROM auditoria WHERE acao='plataforma.mercadopago_credenciais' AND depois NOT LIKE '%Segredo%'") === '1', 'auditoria registra sem os valores');

  // em branco mantém
  await P.fill('[name=client_id]', '9999999999999999');
  await nav(P, P.click('button:has-text("Salvar chaves de produção")'));
  ok(sql("SELECT COUNT(*) FROM parametro_sistema WHERE chave='mp_client_secret' AND valor<>''") === '1' && sql("SELECT valor FROM parametro_sistema WHERE chave='mp_client_id'") === '9999999999999999', 'campo em branco mantém o valor atual');

  // o Conectar do produtor usa as chaves do banco (no lugar do .env)
  await P.goto(BASE + '/painel/financeiro');
  await nav(P, P.click('button:has-text("Conectar Mercado Pago")'));
  ok(clientIdUsado === '9999999999999999', 'Conectar usa o Client ID cadastrado na Administração');

  // pagamento de teste: Teste (padrão) exige o Access Token de teste
  await P.goto(BASE + '/admin/mercadopago');
  ok(await P.inputValue('#adm-valor-teste') === '5.00' && await P.locator('#ambiente-teste').count() === 1 && await P.locator('#ambiente-producao input[name=access_token]').count() === 1
    && await P.locator('#ambiente-teste input[name=teste_access_token]').count() === 1, 'dois blocos (teste e produção), cada um com seus 4 campos e R$ 5,00');
  await nav(P, P.click('button:has-text("Pagar teste no Mercado Pago")'));
  ok((await P.content()).includes('Cadastre primeiro as credenciais de teste'), 'sem credenciais de teste, avisa');
  await P.fill('[name=teste_client_id]', 'teste-id'); await P.fill('[name=teste_client_secret]', 'teste-segredo');
  await nav(P, P.click('button:has-text("Salvar chaves de teste")'));
  ok(sql("SELECT COUNT(*) FROM parametro_sistema WHERE chave='mp_teste_client_secret' AND valor NOT LIKE '%teste-segredo%'") === '1', 'Client Secret de teste gravado criptografado');
  await nav(P, P.click('button:has-text("Pagar teste no Mercado Pago")'));
  await P.goto(await P.inputValue('#link-teste'));
  ok(autorizacaoPref === 'Bearer APP_USR-teste-cc', 'Teste com Client ID/Secret de teste: gera o token de teste e cria a preferência com ele');
  await P.goto(BASE + '/admin/mercadopago');
  await P.fill('[name=teste_access_token]', 'token-errado');
  await nav(P, P.click('button:has-text("Salvar chaves de teste")'));
  ok((await P.content()).includes('começa com APP_USR-'), 'Access Token de teste em formato errado é recusado');
  await P.fill('[name=teste_access_token]', 'APP_USR-teste-123');
  await nav(P, P.click('button:has-text("Salvar chaves de teste")'));
  ok(sql("SELECT valor NOT LIKE '%APP_USR%' FROM parametro_sistema WHERE chave='mp_teste_access_token'") === '1', 'Access Token de teste gravado criptografado');
  await nav(P, P.click('button:has-text("Pagar teste no Mercado Pago")'));
  ok(await P.locator('.link-teste__qr svg').count() === 1 && (await P.inputValue('#link-teste')).includes('/admin/mercadopago/teste/retorno'), 'mostra o link para copiar e o QR Code');
  await P.goto(await P.inputValue('#link-teste'));
  ok(autorizacaoPref === 'Bearer APP_USR-teste-123' && preferencia.items[0].unit_price === 5 && preferencia.items[0].currency_id === 'BRL'
    && !('notification_url' in preferencia) && preferencia.external_reference.startsWith('teste-'), 'Teste: preferência de R$ 5 com o Access Token de teste, sem notification_url (usa o webhook do painel)');
  let ret = await P.content();
  ok(ret.includes('123456') && ret.includes('987654') && ret.includes('Aprovado') && ret.includes('Credenciais de teste') && ret.includes('conferido na API'), 'retorno mostra IDs, status e "Credenciais de teste"');

  // retorno aberto numa janela sem login: mostra os IDs, sem consultar a API
  const anon = await (await b.newContext()).newPage();
  const r = await anon.goto(BASE + '/admin/mercadopago/teste/retorno?payment_id=555&merchant_order_id=666&status=approved');
  const txt = await anon.content();
  ok(r.status() === 200 && txt.includes('555') && txt.includes('666') && !txt.includes('conferido na API') && txt.includes('anote os IDs'), 'retorno sem login mostra os IDs (sem consultar a API)');
  ok((await anon.goto(BASE + '/admin/mercadopago')).status() !== 200 || anon.url().endsWith('/entrar'), 'o resto da Administração continua fechado');

  // verificar de quem é o token, em cada ambiente
  await P.goto(BASE + '/admin/mercadopago');
  await nav(P, P.click('#ambiente-teste button:has-text("Verificar de quem é o token")'));
  ok((await P.locator('#ambiente-teste .verificacao').innerText()).includes('TESTUSER123') && (await P.locator('#ambiente-teste .verificacao').innerText()).includes('Conta de teste'), 'teste: token de conta de teste');
  await nav(P, P.click('#ambiente-producao button:has-text("Verificar de quem é o token")'));
  ok((await P.locator('#ambiente-producao .verificacao').innerText()).includes('BHSOFTWARE') && (await P.locator('#ambiente-producao .verificacao').innerText()).includes('Conta real'), 'produção: token de conta real');

  // Produção usa o token da aplicação (client_credentials)
  await P.goto(BASE + '/admin/mercadopago');
  await nav(P, P.click('button:has-text("Pagar de verdade no Mercado Pago")'));
  await P.goto(await P.inputValue('#link-teste'));
  ret = await P.content();
  ok(autorizacaoPref === 'Bearer APP-TOKEN-BH' && preferencia.notification_url.endsWith('/webhooks/mercadopago') && ret.includes('Produção') && ret.includes('Aprovado'), 'Produção: usa o token da aplicação e manda o webhook');

  // assinatura secreta de teste dos webhooks (mesma URL)
  await P.goto(BASE + '/admin/mercadopago');
  await P.fill('[name=teste_webhook_secret]', 'wh-segredo-de-teste');
  await nav(P, P.click('button:has-text("Salvar chaves de teste")'));
  const assinar = (segredo, id, rid) => { const ts = String(Date.now()); return `ts=${ts},v1=${crypto.createHmac('sha256', segredo).update(`id:${id};request-id:${rid};ts:${ts};`).digest('hex')}`; };
  for (const [idEv, segredo] of [['ev-teste', 'wh-segredo-de-teste'], ['ev-prod', 'assinatura-wh-9876'], ['ev-falso', 'outro']]) {
    await P.request.post(BASE + '/webhooks/mercadopago?data.id=77&type=payment', { data: { id: idEv, type: 'payment', data: { id: '77' } }, headers: { 'x-request-id': 'r-' + idEv, 'x-signature': assinar(segredo, '77', 'r-' + idEv) } });
  }
  ok(sql("SELECT GROUP_CONCAT(CONCAT(id_evento_externo, ':', assinatura_valida) ORDER BY id_evento_externo) FROM webhook_evento WHERE id_evento_externo LIKE 'ev-%'") === 'ev-falso:0,ev-prod:1,ev-teste:1',
    'aviso aceito com a assinatura de produção ou a de teste; outra é ignorada');
  await P.reload();
  ok((await P.content()).includes('Válida (teste)'), 'lista marca o aviso assinado com a assinatura de teste');

  // Access Token de produção cadastrado passa a ser usado no lugar do client_credentials
  await P.goto(BASE + '/admin/mercadopago');
  await P.fill('#ambiente-producao [name=access_token]', 'APP_USR-producao-999');
  await nav(P, P.click('button:has-text("Salvar chaves de produção")'));
  ok(sql("SELECT valor NOT LIKE '%producao-999%' FROM parametro_sistema WHERE chave='mp_access_token'") === '1', 'Access Token de produção gravado criptografado');
  await nav(P, P.click('button:has-text("Pagar de verdade no Mercado Pago")'));
  ok(autorizacaoPref === 'Bearer APP_USR-producao-999', 'Produção usa o Access Token de produção cadastrado');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  sql("DELETE FROM parametro_sistema WHERE chave LIKE 'mp\\_%'");
  await b.close(); falso.close();
})();
