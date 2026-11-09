// App de check-in da equipe (/app, celular): eventos, abas Vendas/Check-in/Ferramentas, leitor (número e QR assinado),
// participantes com check-in e a regra "só no dia do evento". Banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-app.js
const { chromium, devices } = require('playwright');
const { execSync } = require('child_process');
const formularioLegado = require('./_formulario-legado');
const crypto = require('crypto');
const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const APP_KEY = (fs.readFileSync(__dirname + '/../../.env', 'utf8').match(/^APP_KEY=(.*)$/m) || [])[1].trim();
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const codEv = (slug) => sql(`SELECT SUBSTRING(REPLACE(uuid,'-',''),1,10) FROM evento WHERE slug='${slug}'`);
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);
const qr = (codigo) => `FF1.${codigo}.${crypto.createHmac('sha256', APP_KEY).update(codigo).digest('hex').slice(0, 16)}`;

(async () => {
  const b = await chromium.launch(); const erros = [];
  const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage(); P.on('pageerror', (e) => erros.push(e.message));
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Portaria'], ['email', 'bruno@app.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));
  formularioLegado(sql);
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Show do App');
  await P.fill('[name=data_inicio]', local(3)); await P.fill('[name=data_fim]', local(4, '02:00'));
  await P.fill('[name="local[nome]"]', 'Estação Slod'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  const f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('[name=quantidade]').fill('100');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  await nav(P, P.click('button:has-text("Publicar evento")'));

  const V = await (await b.newContext()).newPage();
  await V.goto(BASE + '/e/' + codEv('show-do-app'));
  await V.selectOption('select[name^="q["]', '3');
  await nav(V, V.click('button:has-text("Garantir ingresso")'));
  await V.fill('[name=email]', 'ana@publico.test'); await V.fill('[name=nome]', 'Ana Compradora'); await V.click('[data-continuar]'); await V.waitForSelector('[name=cpf]:visible'); await V.fill('[name=cpf]', '99603082430');
  for (const [i, nome, cpf] of [[0, 'Ana Compradora', '529.982.247-25'], [1, 'Pedro Amigo', '153.509.460-56'], [2, 'Carla Prima', '111.444.777-35']]) {
    await V.fill(`[name="participantes[${i}][nome]"]`, nome); await V.fill(`[name="participantes[${i}][cpf]"]`, cpf);
  }
  await V.check('[name=aceite_termos]');
  await nav(V, V.click('button:has-text("Confirmar inscrição")'));
  const idEv = sql("SELECT id FROM evento WHERE slug='show-do-app'");
  sql(`UPDATE evento SET abertura_portoes=NULL WHERE id=${idEv}`); // o formulário preenche os portões com o início do evento
  const ing = (nome, campo) => sql(`SELECT i.${campo} FROM ingresso i JOIN participante p ON p.ingresso_id=i.id WHERE p.nome='${nome}'`);

  // evento acontecendo agora
  sql(`UPDATE evento SET data_inicio = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE), data_fim = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 4 HOUR) WHERE id=${idEv}`);
  sql(`UPDATE lote SET venda_fim = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 4 HOUR) WHERE evento_id=${idEv}`);

  // ---- app no celular ----
  const M = await (await b.newContext({ ...devices['iPhone 13'] })).newPage(); M.on('pageerror', (e) => erros.push(e.message));
  await M.goto(BASE + '/app');
  ok(M.url().includes('/entrar'), 'app pede login da equipe');
  await M.fill('[name=email]', 'bruno@app.test'); await M.fill('[name=senha]', 'Senha123');
  await nav(M, M.click('button[type=submit]'));
  ok(M.url().endsWith('/app') && (await M.content()).includes('Hoje — check-in liberado') && (await M.content()).includes('Check-in aberto') && (await M.content()).includes('Show do App'), 'depois do login volta ao app: evento de hoje com check-in aberto');
  ok((await M.request.get(BASE + '/app.webmanifest')).status() === 200 && (await M.request.get(BASE + '/app-sw.js')).status() === 200, 'manifesto e service worker publicados (app instalável)');
  ok((await M.getAttribute('a:has-text("Abrir o painel completo")', 'href')).endsWith('/painel'), 'atalho "Abrir o painel completo" antes dos eventos');
  await nav(M, M.click('a.ck-evento'));
  ok((await M.locator('.ck-abas').innerText()).replace(/\s+/g, ' ').includes('VENDAS CHECK-IN FERRAMENTAS') || (await M.locator('.ck-abas a').count()) === 3, 'abas Vendas / Check-in / Ferramentas');
  ok(await M.isVisible('.ck-abas a.ativa:has-text("Vendas")'), 'produtor abre o evento direto na aba Vendas');
  await nav(M, M.click('.ck-abas a:has-text("Check-in")'));
  ok((await M.locator('[data-num=entraram]').innerText()) === '0' && (await M.locator('[data-num=restam]').innerText()) === '3' && (await M.content()).includes('Check-in aberto'), 'Check-in: 0 realizados, 3 restantes, aberto');
  await nav(M, M.click('.ck-abas a:has-text("Vendas")'));
  ok((await M.content()).includes('Ingressos vendidos') && (await M.locator('.ck-item').count()) >= 1, 'Vendas: totais e por ingresso');
  ok((await M.content()).includes('Líquido a receber') && (await M.content()).includes('Vendidos hoje') && (await M.content()).includes('Taxa da plataforma')
    && await M.isVisible('.ck-rosca') && await M.isVisible('a:has-text("Vender ingressos")') && (await M.content()).includes('Ingressos para venda'), 'Vendas: painel completo (hoje, taxa, líquido, gráfico, lotes, Vender ingressos)');
  if (process.env.PRINT) await M.screenshot({ path: process.env.PRINT, fullPage: true });
  await nav(M, M.click('.ck-abas a:has-text("Ferramentas")'));
  ok((await M.content()).includes('Participantes') && (await M.content()).includes('Divulgue') && (await M.content()).includes('Códigos promocionais'), 'Ferramentas conforme o nível (produtor vê todas)');

  // ---- leitor ----
  await M.goto(BASE + '/app/evento/' + sql(`SELECT uuid FROM evento WHERE id=${idEv}`) + '/ler');
  ok(await M.locator('[data-manual]').isVisible() && await M.locator('.ck-mira').count() === 1, 'leitor aberto (câmera + digitar o número)');
  await M.fill('[data-manual] [name=codigo]', String(ing('Ana Compradora', 'numero'))); await M.click('[data-manual] button');
  await M.waitForSelector('.ck-resultado.mostrar');
  let t = await M.locator('.ck-resultado').innerText();
  ok(t.includes('Entrada liberada') && t.includes('Ana Compradora') && (await M.getAttribute('.ck-resultado', 'class')).includes('aceito'), 'número digitado: tela verde com o nome');
  await M.click('.ck-resultado');
  await M.fill('[data-manual] [name=codigo]', String(ing('Ana Compradora', 'numero'))); await M.click('[data-manual] button');
  await M.waitForSelector('.ck-resultado.mostrar');
  ok((await M.getAttribute('.ck-resultado', 'class')).includes('alerta') && (await M.locator('.ck-resultado').innerText()).includes('já utilizado'), 'segunda leitura: alerta "já utilizado"');
  await M.click('.ck-resultado');
  // o que a câmera lê é o conteúdo do QR: o leitor envia o mesmo POST
  const viaQr = await M.evaluate(async ([url, codigo]) => (await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ codigo }) })).json(),
    [await M.getAttribute('[data-leitor]', 'data-url'), qr(ing('Pedro Amigo', 'codigo_qr'))]);
  ok(viaQr.aceito && viaQr.ingresso.nome === 'Pedro Amigo', 'QR assinado lido pela câmera: entrada liberada');
  const anon = await (await b.newContext()).newPage();
  const semLogin = await anon.request.post(BASE + '/painel/checkin/' + sql(`SELECT uuid FROM evento WHERE id=${idEv}`) + '/registrar', { data: { codigo: qr(ing('Carla Prima', 'codigo_qr')) }, headers: { Accept: 'application/json' }, maxRedirects: 0 });
  ok(semLogin.status() !== 200 && ing('Carla Prima', 'status') === 'valido', 'sem login da equipe, ler o QR não dá baixa (só texto)');

  // ---- participantes ----
  await M.goto(BASE + '/app/evento/' + sql(`SELECT uuid FROM evento WHERE id=${idEv}`) + '/participantes?f=faltam');
  ok((await M.locator('.ck-pessoa').count()) === 1 && (await M.content()).includes('Carla Prima'), 'filtro "Faltam": só quem não entrou');
  M.once('dialog', (d) => d.accept());
  await M.click('[data-checkin]');
  await M.waitForSelector('.ck-pessoa .ok');
  ok(ing('Carla Prima', 'status') === 'utilizado', 'check-in pela lista de participantes');

  // ---- só no dia do evento ----
  sql(`UPDATE evento SET data_inicio = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 DAY), data_fim = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 50 HOUR), abertura_portoes = NULL WHERE id=${idEv}`);
  sql(`UPDATE ingresso SET status='valido', dentro_do_evento=0 WHERE evento_id=${idEv}`);
  await M.goto(BASE + '/app/evento/' + sql(`SELECT uuid FROM evento WHERE id=${idEv}`) + '/ler');
  ok((await M.content()).includes('Check-in fechado') && (await M.content()).includes('O check-in abre em') && (await M.locator('[data-manual]').count()) === 0, 'fora do dia do evento: leitor fechado com a hora de abertura');
  const fora = await M.evaluate(async ([url, codigo]) => (await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ codigo }) })).json(),
    [await M.getAttribute('[data-leitor]', 'data-url'), qr(ing('Pedro Amigo', 'codigo_qr'))]);
  ok(!fora.aceito && fora.resultado === 'fora_do_horario' && fora.mensagem.includes('abre em') && ing('Pedro Amigo', 'status') === 'valido', 'servidor recusa o check-in antes do dia do evento');
  // abertura dos portões em outro dia é recusada no cadastro
  await P.goto(BASE + '/painel/eventos/' + sql(`SELECT uuid FROM evento WHERE id=${idEv}`) + '/editar');
  await P.fill('[name=data_inicio]', local(5)); await P.fill('[name=data_fim]', local(6, '02:00')); await P.fill('[name=abertura_portoes]', local(4, '18:00'));
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  ok((await P.content()).includes('tem de ser no mesmo dia do evento'), 'abertura dos portões em outro dia é recusada');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
