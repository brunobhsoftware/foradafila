// Teste de ponta a ponta da área do produtor (Playwright). Rode contra um banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-produtor.js
const { chromium } = require('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const OUT = process.env.OUT || require('os').tmpdir() + '/ff-shots/';
const fs = require('fs'); fs.mkdirSync(OUT, { recursive: true });
const ok = (cond, msg) => { console.log((cond ? 'PASSOU ' : 'FALHOU ') + msg); if (!cond) process.exitCode = 1; };
const local = (dias, hora = '20:00') => { const d = new Date(Date.now() + dias * 864e5); return d.toISOString().slice(0, 10) + 'T' + hora; };

async function cadastrar(page, { nome, email, tipo, doc, razao, fantasia }) {
  await page.goto(BASE + '/cadastro');
  await page.fill('[name=nome]', nome);
  await page.fill('[name=email]', email);
  await page.fill('[name=telefone]', '11987654321');
  await page.fill('[name=senha]', 'Senha123');
  await page.fill('[name=senha_confirmation]', 'Senha123');
  await page.check(`[name=tipo_pessoa][value=${tipo}]`, { force: true });
  await page.fill('[name=documento]', doc);
  await page.fill('[name=razao_social]', razao);
  await page.fill('[name=nome_fantasia]', fantasia);
  await page.fill('[name=email_contato]', 'sac@' + email.split('@')[1]);
  await page.fill('[name=cidade]', 'São Paulo');
  await page.fill('[name=uf]', 'SP');
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await page.check(`[name="aceites[${t}]"]`);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
}

(async () => {
  const browser = await chromium.launch();
  const A = await (await browser.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const erros = [];
  A.on('pageerror', (e) => erros.push(e.message));

  // 0. validação do cadastro (CNPJ inválido)
  await A.goto(BASE + '/cadastro');
  await A.screenshot({ path: OUT + '01-cadastro.png', fullPage: true });
  await cadastrar(A, { nome: 'Bruno Teste', email: 'bruno@a.test', tipo: 'PJ', doc: '11.222.333/0001-00', razao: 'Choperia X Ltda', fantasia: 'Choperia X' });
  ok((await A.content()).includes('CNPJ inválido'), 'cadastro recusa CNPJ inválido');

  // 1. cadastro do produtor A
  await cadastrar(A, { nome: 'Bruno Teste', email: 'bruno@a.test', tipo: 'PJ', doc: '11.222.333/0001-81', razao: 'Choperia X Ltda', fantasia: 'Choperia X' });
  ok(A.url().endsWith('/painel'), 'cadastro leva ao painel: ' + A.url());
  ok((await A.content()).includes('Conta criada'), 'mensagem de conta criada');
  await A.screenshot({ path: OUT + '02-painel-vazio.png', fullPage: true });

  // 2. criar evento
  await A.goto(BASE + '/painel/eventos/novo');
  await A.fill('[name=titulo]', 'Festival Fora da Fila');
  await A.selectOption('[name=categoria_id]', { label: 'Shows e música' });
  await A.fill('[name=resumo]', 'O primeiro evento piloto da plataforma.');
  await A.fill('[name=capacidade]', '500');
  await A.fill('[name=data_inicio]', local(30, '20:00'));
  await A.fill('[name=data_fim]', local(31, '02:00'));
  await A.fill('[name="local[nome]"]', 'Arena Central');
  await A.fill('[name="local[cidade]"]', 'São Paulo');
  await A.fill('[name="local[uf]"]', 'SP');
  await A.check('[name=aceite_cancelamento]'); // declaração de cancelamento (obrigatória)
  await Promise.all([A.waitForNavigation(), A.click('.acoes-form button[type=submit]')]);
  ok((await A.content()).includes('Evento criado como rascunho'), 'evento criado como rascunho');
  const urlEvento = A.url();
  ok(await A.isDisabled('button:has-text("Publicar evento")'), 'publicar desabilitado sem lote');

  // 3. tipo + lote
  await Promise.all([A.waitForNavigation(), A.click('button:has-text("Criar tipo")')]);
  ok((await A.content()).includes('Tipo de ingresso “Ingresso” criado'), 'tipo de ingresso criado');
  await A.click('summary:has-text("Adicionar lote")');
  const f = A.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('input[name=cobranca][value=gratuito]').check({ force: true });
  await f.locator('[name=quantidade]').fill('600');
  await Promise.all([A.waitForNavigation(), f.locator('button:has-text("Adicionar lote")').click()]);
  ok((await A.content()).includes('passa da capacidade'), 'lote acima da capacidade é recusado');
  if (!(await A.locator('details:has(summary:has-text("Adicionar lote"))').getAttribute('open') !== null)) await A.click('summary:has-text("Adicionar lote")');
  const f2 = A.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f2.locator('input[name=cobranca][value=gratuito]').check({ force: true });
  await f2.locator('[name=quantidade]').fill('300');
  await Promise.all([A.waitForNavigation(), f2.locator('button:has-text("Adicionar lote")').click()]);
  ok((await A.content()).includes('Lote “1º lote” criado'), 'lote gratuito criado');

  // 4. publicar (o trigger confere termo, lote, capacidade e data)
  await Promise.all([A.waitForNavigation(), A.click('button:has-text("Publicar evento")')]);
  ok((await A.content()).includes('Evento publicado'), 'evento publicado');
  await A.screenshot({ path: OUT + '03-evento.png', fullPage: true });

  // 5. lote pago num evento publicado sem conta de recebimento: cria (rascunho de venda) — publicação já ocorreu
  // 6. convite para a portaria, só neste evento
  await A.goto(BASE + '/painel/equipe');
  const conv = A.locator('aside form');
  await conv.locator('[name=nome]').fill('Carla Portaria');
  await conv.locator('[name=email]').fill('carla@a.test');
  await conv.locator('[name=perfil_id]').selectOption({ label: 'Check-in (portaria)' });
  await conv.locator('[name=alcance][value=alguns]').check();
  await conv.locator('[name="eventos[]"]').first().check();
  await Promise.all([A.waitForNavigation(), conv.locator('button:has-text("Gerar convite")').click()]);
  const link = await A.inputValue('#link-convite');
  ok(link.includes('/convite/'), 'link de convite gerado');
  await A.screenshot({ path: OUT + '04-equipe.png', fullPage: true });

  // 7. último Dono não pode ser rebaixado
  await A.goto(BASE + '/painel/equipe');
  const linhaDono = A.locator('tr:has-text("(você)")');
  await linhaDono.locator('summary').click();
  await linhaDono.locator('[name=perfil_id]').selectOption({ label: 'Gerente' });
  await Promise.all([A.waitForNavigation(), linhaDono.locator('button:has-text("Salvar")').click()]);
  ok((await A.content()).includes('pelo menos um Produtor ativo'), 'último Produtor (dono) não pode ser rebaixado');

  await A.goto(BASE + '/painel/perfis');
  await A.screenshot({ path: OUT + '05-perfis.png', fullPage: true });
  await A.goto(BASE + '/painel');
  await A.screenshot({ path: OUT + '06-painel.png', fullPage: true });

  // 8. Carla aceita o convite
  const C = await (await browser.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  await C.goto(link);
  await C.fill('[name=senha]', 'Portaria1');
  await C.fill('[name=senha_confirmation]', 'Portaria1');
  await Promise.all([C.waitForNavigation(), C.click('button:has-text("Aceitar convite")')]);
  const menuC = await C.locator('.menu-lateral nav').innerText();
  ok(C.url().includes('/painel') && (await C.content()).includes('Convite aceito'), 'convite aceito');
  ok(menuC.includes('Check-in') && !menuC.includes('Equipe') && !menuC.includes('Eventos'), 'portaria só vê Check-in no menu: ' + menuC.replace(/\s+/g, ' '));
  const r403 = await C.goto(BASE + '/painel/equipe');
  ok(r403.status() === 403, 'portaria recebe 403 em Equipe');
  const rEv = await C.goto(urlEvento);
  ok(rEv.status() === 403, 'portaria não abre a página do evento (sem evento.ver): ' + rEv.status());
  ok((await (await C.goto(link)).status()) === 404, 'link de convite não funciona duas vezes');

  // 9. Organizador B não enxerga dados de A
  const B = await (await browser.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  await cadastrar(B, { nome: 'Maria B', email: 'maria@b.test', tipo: 'PF', doc: '529.982.247-25', razao: 'Maria Bezerra', fantasia: 'Festas da Maria' });
  ok(B.url().endsWith('/painel'), 'cadastro PF (CPF) ok');
  const rB = await B.goto(urlEvento);
  ok(rB.status() === 404, 'organizador B recebe 404 no evento de A: ' + rB.status());
  await B.goto(BASE + '/painel/eventos');
  ok(!(await B.content()).includes('Festival Fora da Fila'), 'lista de eventos de B não mostra eventos de A');

  // 10. bloqueio após 5 senhas erradas
  const L = await (await browser.newContext()).newPage();
  for (let i = 0; i < 5; i++) {
    await L.goto(BASE + '/entrar');
    await L.fill('[name=email]', 'maria@b.test'); await L.fill('[name=senha]', 'errada' + i);
    await Promise.all([L.waitForNavigation(), L.click('button[type=submit]')]);
  }
  await L.fill('[name=email]', 'maria@b.test'); await L.fill('[name=senha]', 'Senha123');
  await Promise.all([L.waitForNavigation(), L.click('button[type=submit]')]);
  ok((await L.content()).includes('Acesso bloqueado'), 'login bloqueado após 5 tentativas');

  // 11. celular
  const M = await (await browser.newContext({ ...require('playwright').devices['iPhone 13'] })).newPage();
  await M.goto(BASE + '/entrar');
  await M.fill('[name=email]', 'bruno@a.test'); await M.fill('[name=senha]', 'Senha123');
  await Promise.all([M.waitForNavigation(), M.click('button[type=submit]')]);
  await M.screenshot({ path: OUT + '07-celular-painel.png' });
  await M.click('[data-menu-alternar]'); await M.waitForTimeout(300);
  await M.screenshot({ path: OUT + '08-celular-menu.png' });
  ok(await M.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'sem rolagem horizontal no celular');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await browser.close();
})();
