// Teste de ponta a ponta da Administração da plataforma: acesso só para admin_plataforma e
// cadastro das chaves do Mercado Pago (guardadas no banco, segredos criptografados).
// Rode contra o Laravel do fluxo-financeiro (porta 8001, Mercado Pago falso na 8099):
//   BASE=http://127.0.0.1:8001 node tests/e2e/fluxo-admin.js
const { chromium } = require('playwright');
const http = require('http');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8001';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

let clientIdUsado = null; let segredoUsado = null;
const falso = http.createServer((req, res) => {
  const u = new URL(req.url, 'http://x');
  if (u.pathname === '/authorization') { clientIdUsado = u.searchParams.get('client_id'); res.writeHead(302, { Location: u.searchParams.get('redirect_uri') + '?error=cancelado&state=' + u.searchParams.get('state') }); return res.end(); }
  let corpo = ''; req.on('data', (d) => { corpo += d; }); req.on('end', () => { segredoUsado = new URLSearchParams(corpo).get('client_secret'); res.writeHead(400); res.end('{}'); });
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

  await nav(P, P.click('button:has-text("Salvar chaves")'));
  ok((await P.content()).includes('Preencha pelo menos um campo'), 'tudo em branco é recusado');
  await P.fill('[name=client_id]', '1234567890123456'); await P.fill('[name=client_secret]', 'SegredoDaBH-abcdWXYZ'); await P.fill('[name=webhook_secret]', 'assinatura-wh-9876');
  await nav(P, P.click('button:has-text("Salvar chaves")'));
  const tela = await P.content();
  ok(tela.includes('Chaves do Mercado Pago salvas') && tela.includes('Configurada'), 'chaves salvas');
  ok(sql("SELECT valor FROM parametro_sistema WHERE chave='mp_client_id'") === '1234567890123456', 'Client ID gravado');
  ok(sql("SELECT COUNT(*) FROM parametro_sistema WHERE chave IN ('mp_client_secret','mp_webhook_secret') AND valor NOT LIKE '%Segredo%' AND valor NOT LIKE '%assinatura%'") === '2', 'segredos criptografados no banco');
  ok(!tela.includes('SegredoDaBH-abcdWXYZ') && tela.includes('••••WXYZ'), 'segredo não volta para a tela (só os 4 últimos)');
  ok(sql("SELECT COUNT(*) FROM auditoria WHERE acao='plataforma.mercadopago_credenciais' AND depois NOT LIKE '%Segredo%'") === '1', 'auditoria registra sem os valores');

  // em branco mantém
  await P.fill('[name=client_id]', '9999999999999999');
  await nav(P, P.click('button:has-text("Salvar chaves")'));
  ok(sql("SELECT COUNT(*) FROM parametro_sistema WHERE chave='mp_client_secret' AND valor<>''") === '1' && sql("SELECT valor FROM parametro_sistema WHERE chave='mp_client_id'") === '9999999999999999', 'campo em branco mantém o valor atual');

  // o Conectar do produtor usa as chaves do banco (no lugar do .env)
  await P.goto(BASE + '/painel/financeiro');
  await nav(P, P.click('button:has-text("Conectar Mercado Pago")'));
  ok(clientIdUsado === '9999999999999999', 'Conectar usa o Client ID cadastrado na Administração');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  sql("DELETE FROM parametro_sistema WHERE chave LIKE 'mp\\_%'");
  await b.close(); falso.close();
})();
