// Teste de ponta a ponta do cupom de DIVULGAÇÃO (V009): criar (campos de desconto/limites/validade somem),
// link com o código, inscrição pelo link (campo já preenchido), contagem de ingressos na lista, código digitado,
// código inválido e cancelamento (sai da contagem). Banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-divulgacao.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const formularioLegado = require('./_formulario-legado');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const codEv = (slug) => sql(`SELECT SUBSTRING(REPLACE(uuid,'-',''),1,10) FROM evento WHERE slug='${slug}'`);
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

(async () => {
  const b = await chromium.launch(); const erros = [];
  const P = await (await b.newContext({ viewport: { width: 1360, height: 900 }, permissions: ['clipboard-read', 'clipboard-write'] })).newPage();
  P.on('pageerror', (e) => erros.push(e.message)); P.on('dialog', (d) => d.accept());
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Divulga'], ['email', 'bruno@divulga.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));
  formularioLegado(sql);
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Festa Divulgada');
  await P.fill('[name=data_inicio]', local(12)); await P.fill('[name=data_fim]', local(13, '02:00'));
  await P.fill('[name="local[nome]"]', 'Local'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  const f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('[name=quantidade]').fill('100');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  await nav(P, P.click('button:has-text("Publicar evento")'));

  // ---- criar cupom de divulgação ----
  await P.goto(BASE + '/painel/cupons');
  const novo = P.locator('form:has(input[name=_cupom][value=novo])');
  await novo.locator('[name=tipo_desconto]').selectOption('divulgacao');
  ok(!(await novo.locator('[name=valor]').isVisible()) && !(await novo.locator('[name=max_usos]').isVisible()) && !(await novo.locator('[name=valido_ate]').isVisible())
    && !(await novo.locator('[name=valor_minimo_pedido]').isVisible()) && !(await novo.locator('[name=max_usos_cpf]').isVisible()), 'divulgação esconde desconto, limites, pedido mínimo e validade');
  ok((await novo.locator('[data-so-divulgacao]').innerText()).includes('quantos ingressos cada pessoa divulgou'), 'explica para que serve');
  await novo.locator('[name=codigo]').fill('joao'); await novo.locator('[name=descricao]').fill('Promoter João');
  await novo.locator('[name=evento]').selectOption({ label: 'Festa Divulgada' });
  await nav(P, novo.locator('button:has-text("Criar cupom")').click());
  ok((await P.content()).includes('Cupom JOAO criado'), 'cupom de divulgação criado');
  ok(sql("SELECT CONCAT_WS('|', tipo_desconto, valor, IFNULL(max_usos,'-'), IFNULL(valido_ate,'-')) FROM cupom WHERE codigo='JOAO'") === 'divulgacao|0.00|-|-', 'gravado sem desconto, sem limite e sem validade');
  let linha = P.locator('tr:has-text("JOAO")').first();
  ok((await linha.innerText()).includes('Divulgação') && (await linha.innerText()).includes('sem limite'), 'lista mostra "Divulgação" e "sem limite"');
  await linha.locator('button:has-text("Copiar link")').click();
  const link = await P.evaluate(() => navigator.clipboard.readText());
  ok(/\/e\/[0-9a-f]{10}\?cupom=JOAO$/.test(link), 'copia o link do evento (código fixo) com o cupom: ' + link);

  // ---- inscrição pelo link ----
  const V = await (await b.newContext()).newPage();
  await V.goto(link);
  await V.selectOption('select[name^="q["]', '2');
  await nav(V, V.click('button:has-text("Garantir ingresso")'));
  ok(await V.inputValue('[name=cupom]') === 'JOAO', 'inscrição já vem com o código do link');
  await V.fill('[name=email]', 'ana@publico.test'); await V.fill('[name=nome]', 'Ana Compradora'); await V.click('[data-continuar]'); await V.waitForSelector('[name=cpf]:visible'); await V.fill('[name=cpf]', '54323194897');
  await V.fill('[name="participantes[0][nome]"]', 'Ana Compradora'); await V.fill('[name="participantes[1][nome]"]', 'Pedro Amigo');
  await V.check('[name=aceite_termos]');
  await nav(V, V.click('button:has-text("Confirmar inscrição")'));
  ok(/\/pedido\/.*\/ingressos$/.test(V.url()), 'inscrição confirmada');
  ok(sql("SELECT COUNT(*) FROM pedido p JOIN cupom c ON c.id=p.cupom_id WHERE c.codigo='JOAO'") === '1' && sql("SELECT usos FROM cupom WHERE codigo='JOAO'") === '1', 'pedido ligado ao cupom');

  // ---- código digitado e código inválido ----
  const W = await (await b.newContext()).newPage();
  await W.goto(BASE + '/e/' + codEv('festa-divulgada'));
  // cupom na página do evento: "Tem um cupom de desconto?" → Aplicar
  await W.click('summary:has-text("Tem um cupom")');
  await W.fill('[data-cupom-publico] input', 'naoexiste'); await W.click('[data-cupom-aplicar]');
  await W.waitForFunction(() => document.querySelector('[data-cupom-msg]').textContent.length > 0);
  ok((await W.locator('[data-cupom-msg]').innerText()).includes('não encontrado'), 'cupom inválido avisa na página do evento');
  await W.fill('[data-cupom-publico] input', 'joao'); await W.click('[data-cupom-aplicar]');
  await W.waitForFunction(() => document.querySelector('[data-cupom-msg]').textContent.includes('JOAO'));
  ok((await W.locator('[data-cupom-msg]').innerText()).includes('Código JOAO registrado'), 'cupom válido é aceito na página do evento');
  await W.selectOption('select[name^="q["]', '1');
  await nav(W, W.click('button:has-text("Garantir ingresso")'));
  ok(await W.inputValue('[name=cupom]') === 'JOAO', 'cupom aplicado no evento segue para a compra');
  await W.fill('[name=email]', 'carla@publico.test'); await W.fill('[name=nome]', 'Carla Prima'); await W.click('[data-continuar]'); await W.waitForSelector('[name=cpf]:visible'); await W.fill('[name=cpf]', '75749118606');
  await W.fill('[name="participantes[0][nome]"]', 'Carla Prima'); await W.fill('[name=cupom]', 'naoexiste');
  await W.check('[name=aceite_termos]');
  await nav(W, W.click('button:has-text("Confirmar inscrição")'));
  ok((await W.content()).includes('Código não encontrado'), 'código inválido é recusado');
  await W.fill('[name=cupom]', 'joao');
  await W.check('[name=aceite_termos]');
  await nav(W, W.click('button:has-text("Confirmar inscrição")'));
  ok(/\/pedido\/.*\/ingressos$/.test(W.url()) && sql("SELECT usos FROM cupom WHERE codigo='JOAO'") === '2', 'código digitado (minúsculas) também conta');

  // ---- contagem na lista ----
  await P.reload();
  linha = P.locator('tr:has-text("JOAO")').first();
  ok((await linha.locator('td').nth(3).innerText()).trim() === '3', 'lista mostra 3 ingressos divulgados');

  // ---- cancelamento sai da contagem ----
  await P.goto(BASE + '/painel/participantes');
  await P.locator('tr:not(.linha-acoes):has-text("Pedro Amigo") button:has-text("Ações")').click();
  await P.locator('tr.linha-acoes:visible button:has-text("Cancelar este ingresso")').click();
  await nav(P, P.locator('tr.linha-acoes:visible button:has-text("Cancelar ingresso")').click());
  await P.goto(BASE + '/painel/cupons');
  ok((await P.locator('tr:has-text("JOAO")').first().locator('td').nth(3).innerText()).trim() === '2', 'ingresso cancelado sai da contagem');

  // desativado: código passa a ser recusado
  await nav(P, P.locator('tr:has-text("JOAO")').first().locator('button:has-text("Desativar")').click());
  const X = await (await b.newContext()).newPage();
  await X.goto(link); await X.selectOption('select[name^="q["]', '1');
  await nav(X, X.click('button:has-text("Garantir ingresso")'));
  await X.fill('[name=email]', 'dani@publico.test'); await X.fill('[name=nome]', 'Dani'); await X.click('[data-continuar]'); await X.waitForSelector('[name=cpf]:visible'); await X.fill('[name=cpf]', '25276018987');
  await X.fill('[name="participantes[0][nome]"]', 'Dani');
  await X.check('[name=aceite_termos]');
  await nav(X, X.click('button:has-text("Confirmar inscrição")'));
  ok((await X.content()).includes('Código não encontrado, desativado'), 'código desativado é recusado');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
