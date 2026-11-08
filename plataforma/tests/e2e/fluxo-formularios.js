// Formulários de inscrição (V015): padrão "Nome completo + E-mail" criado sozinho, formulário próprio com campos do catálogo e
// personalizados, evento escolhe o formulário, a compra pede os campos e grava em participante / participante_resposta. Banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-formularios.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const codEv = (slug) => sql(`SELECT SUBSTRING(REPLACE(uuid,'-',''),1,10) FROM evento WHERE slug='${slug}'`);
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

(async () => {
  const b = await chromium.launch(); const erros = [];
  const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage(); P.on('pageerror', (e) => erros.push(e.message));
  P.on('dialog', (d) => d.accept());
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Form'], ['email', 'bruno@form.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));

  // ---- padrão criado sozinho ----
  await nav(P, P.click('.menu-lateral a:has-text("Formulários")'));
  let t = (await P.locator('table.tabela').innerText()).replace(/\s+/g, ' ');
  ok(t.includes('Padrão — Nome completo e e-mail') && t.includes('Padrão'), 'formulário padrão criado: Nome completo e e-mail');
  ok(sql("SELECT GROUP_CONCAT(CONCAT(cc.codigo,':',fc.obrigatorio) ORDER BY fc.ordem) FROM formulario_campo fc JOIN campo_catalogo cc ON cc.id=fc.campo_catalogo_id") === 'nome:1,email:1', 'padrão com nome completo e e-mail obrigatórios');
  const padraoId = sql('SELECT id FROM formulario WHERE padrao=1');
  ok((await P.locator(`form[action$="/formularios/${padraoId}"] button:has-text("Excluir")`).count()) === 0, 'padrão não tem botão de excluir');

  // ---- formulário próprio ----
  await nav(P, P.click('a:has-text("Novo formulário")'));
  await P.fill('[name=nome]', 'Show — completo');
  const idCampo = (cod) => sql(`SELECT id FROM campo_catalogo WHERE codigo='${cod}'`);
  for (const [cod, obrig] of [['email', 1], ['cpf', 1], ['telefone', 0], ['tamanho_camiseta', 0]]) {
    await P.check(`input[name="cat[${idCampo(cod)}][pedir]"]`);
    if (obrig) await P.check(`input[name="cat[${idCampo(cod)}][obrigatorio]"]`);
  }
  await P.fill('input[name="pers[0][rotulo]"]', 'Número da camisa'); await P.selectOption('select[name="pers[0][tipo]"]', 'numero'); await P.check('input[name="pers[0][obrigatorio]"]');
  await P.fill('input[name="pers[1][rotulo]"]', 'Turma'); await P.selectOption('select[name="pers[1][tipo]"]', 'selecao');
  await nav(P, P.click('button:has-text("Salvar formulário")'));
  ok((await P.content()).includes('Informe as opções da lista “Turma”'), 'lista sem opções é recusada');
  await P.fill('input[name="pers[1][opcoes]"]', 'Manhã; Tarde');
  await nav(P, P.click('button:has-text("Salvar formulário")'));
  ok((await P.content()).includes('Formulário “Show — completo” salvo'), 'formulário próprio salvo');
  const formId = sql("SELECT id FROM formulario WHERE nome='Show — completo'");
  ok(sql(`SELECT COUNT(*) FROM formulario_campo WHERE formulario_id=${formId} AND ativo=1`) === '7', '7 campos (nome + 4 do catálogo + 2 personalizados)');

  // ---- evento usando o formulário ----
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Show Formulario');
  await P.fill('[name=data_inicio]', local(15)); await P.fill('[name=data_fim]', local(16, '02:00'));
  await P.fill('[name="local[nome]"]', 'Local'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  ok(await P.locator('input[name=exige_cpf_participante]').count() === 0, 'evento sem as opções antigas de nome/CPF');
  await P.selectOption('select[name=formulario_id]', formId);
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  ok(sql('SELECT CONCAT(formulario_id, "|", exige_cpf_participante) FROM evento') === `${formId}|1`, 'evento ligado ao formulário (CPF obrigatório marcado)');
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  const f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('[name=quantidade]').fill('50');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  await nav(P, P.click('button:has-text("Publicar evento")'));

  // ---- compra pede os campos ----
  const V = await (await b.newContext()).newPage(); V.on('pageerror', (e) => erros.push(e.message));
  await V.goto(BASE + '/e/' + codEv('show-formulario'));
  await V.selectOption('select[name^="q["]', '2');
  await nav(V, V.click('button:has-text("Garantir ingresso")'));
  t = await V.content();
  ok(['Nome completo', 'E-mail', 'CPF', 'Telefone', 'Número da camisa', 'Turma'].every((x) => t.includes(x)) && await V.locator('[name="participantes[1][cpf]"]').count() === 1, 'compra mostra os campos do formulário para cada participante');
  await V.fill('[name=cpf]', '93786579741'); await V.waitForSelector('[name=nome]:visible'); await V.fill('[name=nome]', 'Ana Compradora'); await V.fill('[name=email]', 'ana@form.test');
  await V.fill('[name="participantes[0][nome]"]', 'Ana Compradora'); await V.fill('[name="participantes[0][email]"]', 'ana@form.test');
  await V.fill('[name="participantes[0][cpf]"]', '111.111.111-11'); await V.fill('[name="participantes[0][c' + sql(`SELECT id FROM formulario_campo WHERE rotulo='Número da camisa'`) + ']"]', '10');
  await V.fill('[name="participantes[1][nome]"]', 'Pedro Amigo');
  await V.check('[name=aceite_termos]');
  await V.evaluate(() => document.querySelectorAll('form').forEach((x) => { x.noValidate = true; }));
  await nav(V, V.click('button:has-text("Confirmar inscrição")'));
  t = await V.content();
  ok(t.includes('CPF inválido') && t.includes('cpf') && t.includes('número da camisa'), 'obrigatórios e CPF inválido são recusados');
  const camisa = sql(`SELECT id FROM formulario_campo WHERE rotulo='Número da camisa'`); const turma = sql(`SELECT id FROM formulario_campo WHERE rotulo='Turma'`);
  for (const [i, nome, email, cpf, num] of [[0, 'Ana Compradora', 'ana@form.test', '529.982.247-25', '10'], [1, 'Pedro Amigo', 'pedro@form.test', '153.509.460-56', '7']]) {
    await V.fill(`[name="participantes[${i}][nome]"]`, nome); await V.fill(`[name="participantes[${i}][email]"]`, email);
    await V.fill(`[name="participantes[${i}][cpf]"]`, cpf); await V.fill(`[name="participantes[${i}][c${camisa}]"]`, num);
  }
  await V.fill('[name="participantes[0][telefone]"]', '(31) 98888-7777');
  await V.selectOption('select[name="participantes[0][tamanho_camiseta]"]', { index: 2 });
  await V.selectOption(`select[name="participantes[1][c${turma}]"]`, 'Tarde');
  await V.check('[name=aceite_termos]');
  await nav(V, V.click('button:has-text("Confirmar inscrição")'));
  ok(V.url().endsWith('/minha-conta'), 'inscrição confirmada');
  ok(sql("SELECT CONCAT_WS('|', nome, email, cpf, telefone, tamanho_camiseta) FROM participante WHERE nome='Ana Compradora'").startsWith('Ana Compradora|ana@form.test|52998224725|31988887777|'), 'campos do catálogo gravados no participante');
  ok(sql("SELECT GROUP_CONCAT(r.valor ORDER BY fc.rotulo) FROM participante_resposta r JOIN formulario_campo fc ON fc.id=r.formulario_campo_id JOIN participante p ON p.id=r.participante_id WHERE p.nome='Pedro Amigo'") === '7,Tarde',
    'campos personalizados gravados (número da camisa e turma)');

  // ---- editar abre com os campos marcados ----
  await P.goto(BASE + '/painel/formularios/' + formId);
  ok(await P.isChecked(`input[name="cat[${idCampo('cpf')}][obrigatorio]"]`) && await P.inputValue('input[name="pers[1][opcoes]"]') === 'Manhã; Tarde', 'editar mostra os campos e opções salvos');
  // ---- regras de exclusão ----
  await P.goto(BASE + '/painel/formularios');
  await nav(P, P.locator(`form[action$="/formularios/${formId}"] button:has-text("Excluir")`).click());
  ok((await P.content()).includes('Há eventos usando este formulário'), 'formulário em uso não é excluído');
  await nav(P, P.locator(`form[action$="/formularios/${formId}/padrao"] button`).click());
  ok(sql('SELECT id FROM formulario WHERE padrao=1') === formId, 'tornar padrão');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
