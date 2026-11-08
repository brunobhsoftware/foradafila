// Teste de ponta a ponta da Administração global: empresas, "assumir conta" (impersonação),
// auditoria com o administrador, situação da conta e motor de taxas (V008). Rode contra um banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-admin-empresas.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

const cadastrar = async (P, nome, email, doc, razao, fantasia) => {
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', nome], ['email', email], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', doc], ['razao_social', razao], ['nome_fantasia', fantasia], ['email_contato', email]]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));
};

(async () => {
  const b = await chromium.launch();
  const erros = [];
  // empresa A (cliente)
  const A = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage(); A.on('pageerror', (e) => erros.push(e.message));
  await cadastrar(A, 'Ana Slod', 'ana@slod.test', '41.902.878/0001-08', 'Estacao Slod Ltda', 'Estação Slod');
  ok((await A.goto(BASE + '/admin/empresas')).status() === 404, 'produtor comum não vê a Administração (404)');
  // empresa B, cujo usuário vira administrador da plataforma
  const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage(); P.on('pageerror', (e) => erros.push(e.message));
  await cadastrar(P, 'Bruno Admin', 'bruno@bh.test', '11.222.333/0001-81', 'BH Software Ltda', 'BH Software');
  sql("UPDATE usuario SET admin_plataforma=1 WHERE email='bruno@bh.test'");
  const idA = sql("SELECT id FROM organizador WHERE documento='41902878000108'");
  const uuidA = sql("SELECT uuid FROM organizador WHERE documento='41902878000108'");
  const idAdmin = sql("SELECT id FROM usuario WHERE email='bruno@bh.test'");

  // ---- lista de empresas ----
  await P.goto(BASE + '/admin');
  await nav(P, P.click('.menu-lateral a:has-text("Empresas")'));
  let t = await P.content();
  ok(t.includes('Estação Slod') && t.includes('BH Software') && t.includes('41.902.878/0001-08'), 'lista mostra as empresas com CNPJ');
  await P.fill('[name=busca]', 'slod'); await nav(P, P.click('button:has-text("Filtrar")'));
  t = await P.locator('table.tabela tbody').innerText();
  ok(t.includes('Estação Slod') && !t.includes('BH Software'), 'busca por nome filtra');
  await P.goto(BASE + '/admin/empresas?busca=11222333');
  ok((await P.locator('table.tabela tbody').innerText()).includes('BH Software'), 'busca por CNPJ (só números) filtra');
  await P.goto(BASE + '/admin/empresas?situacao=suspenso');
  ok((await P.content()).includes('Nenhuma empresa encontrada'), 'filtro por situação');

  // ---- detalhes, situação ----
  await P.goto(BASE + '/admin/empresas/' + uuidA);
  t = await P.content();
  ok(t.includes('Usuários ativos') && t.includes('Ana Slod') && t.includes('Nenhuma conta de recebimento'), 'detalhes: números, usuários e integrações');
  await P.selectOption('#situacao [name=situacao]', 'suspenso'); await P.fill('#situacao [name=motivo]', 'Teste de bloqueio');
  await nav(P, P.click('button:has-text("Salvar situação")'));
  ok(sql(`SELECT CONCAT(situacao,'|',situacao_motivo) FROM organizador WHERE id=${idA}`) === 'suspenso|Teste de bloqueio', 'altera a situação (suspenso) com motivo');
  await P.selectOption('#situacao [name=situacao]', 'ativo');
  await nav(P, P.click('button:has-text("Salvar situação")'));

  // ---- motor de taxas: regra padrão e faixas ----
  await P.goto(BASE + '/admin/taxas');
  const editar = P.locator('tr:has-text("Lançamento") button:has-text("Editar")'); await editar.click();
  let f = P.locator("tr.linha-acoes:visible form");
  await f.locator('[name=nome]').fill('Faixa 1 — até 10 mil'); await f.locator('[name=percentual]').fill('5'); await f.locator('[name=valor_fixo]').fill('1');
  await f.locator('[name=valor_minimo]').fill('2'); await f.locator('[name=volume_max]').fill('10000');
  await nav(P, f.locator('button:has-text("Salvar regra")').click());
  ok(sql("SELECT CONCAT_WS('|', percentual, valor_fixo, valor_minimo, volume_max) FROM taxa_regra WHERE nome LIKE 'Faixa 1%'") === '5.000|1.00|2.00|10000.00', 'edita a regra padrão (5% + R$ 1, mínimo R$ 2, até R$ 10 mil)');
  await P.click('summary:has-text("Nova regra ou faixa")');
  f = P.locator('form:has(input[name=_regra][value=nova])');
  await f.locator('[name=nome]').fill('Sobreposta'); await f.locator('[name=percentual]').fill('3'); await f.locator('[name=volume_min]').fill('5000');
  await nav(P, f.locator('button:has-text("Criar regra")').click());
  ok((await P.content()).includes('se sobrepõe à regra ativa'), 'recusa faixa sobreposta');
  f = P.locator('form:has(input[name=_regra][value=nova])');
  await f.locator('[name=nome]').fill('Faixa 2 — acima de 10 mil'); await f.locator('[name=percentual]').fill('3'); await f.locator('[name=volume_min]').fill('10000');
  await f.locator('[name=volume_max]').fill('');
  await nav(P, f.locator('button:has-text("Criar regra")').click());
  ok(sql("SELECT COUNT(*) FROM taxa_regra WHERE ativo=1 AND operacao='ingresso_pago'") === '2', 'cria a segunda faixa (acima de R$ 10 mil)');

  // ---- taxa específica da empresa: simulação ----
  await P.goto(BASE + '/admin/empresas/' + uuidA);
  ok((await P.locator('#taxas').innerText()).includes('R$ 6,00'), 'simulação usa a regra padrão: R$ 100 → R$ 6,00');
  await P.check('#taxas details input[type=checkbox][name=isento]', { force: true }); await P.fill('#taxas details [name=observacao]', 'Parceria de lançamento');
  await nav(P, P.click('#taxas button:has-text("Registrar")'));
  t = await P.locator('#taxas').innerText();
  ok(t.includes('Isenta') && t.includes('vigente') && t.includes('R$ 0,00') && t.includes('Isenção da empresa'), 'isenção registrada e aplicada na simulação');
  P.once('dialog', (d) => d.accept());
  await nav(P, P.click('#taxas button:has-text("Encerrar")'));
  ok((await P.locator('#taxas').innerText()).includes('R$ 6,00'), 'encerrar a isenção volta para a regra padrão');
  await P.fill('#taxas details [name=percentual]', '2');
  await nav(P, P.click('#taxas button:has-text("Registrar")'));
  ok((await P.locator('#taxas').innerText()).includes('R$ 3,00'), 'taxa específica (2% + fixo padrão R$ 1) → R$ 3,00');
  // editar a mesma taxa (sem criar outra)
  await P.click('#taxas button:has-text("Editar")');
  await P.fill('#taxas tr.linha-acoes:visible [name=percentual]', '5');
  await nav(P, P.click('#taxas tr.linha-acoes:visible button:has-text("Salvar alterações")'));
  ok((await P.content()).includes('Taxa da empresa atualizada') && (await P.locator('#taxas').innerText()).includes('R$ 6,00')
    && sql(`SELECT COUNT(*) FROM taxa_organizador WHERE organizador_id=(SELECT id FROM organizador WHERE uuid='${uuidA}') AND (fim IS NULL OR fim >= CURDATE())`) === '1', 'editar a taxa altera a mesma (5% + R$ 1 → R$ 6,00), sem criar outra');

  // ---- assumir conta ----
  await P.goto(BASE + '/admin/empresas');
  await nav(P, P.click('tr:has-text("Estação Slod") button:has-text("Assumir conta")'));
  t = await P.content();
  ok(P.url().endsWith('/painel') && t.includes('Você está navegando como a empresa') && (await P.locator('.faixa-impersonacao').innerText()).includes('Estação Slod'), 'assumir conta: painel da empresa com a faixa de aviso');
  ok((await P.locator('.conta-menu summary').innerText()).includes('Estação Slod'), 'contexto é o da empresa assumida');
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Evento pela Administracao');
  await P.fill('[name=data_inicio]', local(10)); await P.fill('[name=data_fim]', local(11, '02:00'));
  await P.fill('[name="local[nome]"]', 'Local'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  ok(sql(`SELECT organizador_id FROM evento WHERE slug='evento-pela-administracao'`) === idA, 'evento criado na empresa assumida');
  ok(sql(`SELECT CONCAT(usuario_id,'|',admin_impersonando_id) FROM auditoria WHERE acao='evento.criar' ORDER BY id DESC LIMIT 1`) === `${idAdmin}|${idAdmin}`, 'auditoria marca o administrador (admin_impersonando_id)');
  ok(Number(sql(`SELECT acoes FROM impersonacao_log WHERE organizador_id=${idA} ORDER BY id DESC LIMIT 1`)) >= 1, 'log da impersonação conta as ações');

  await nav(P, P.click('.faixa-impersonacao button'));
  t = await P.content();
  ok(P.url().includes('/admin/empresas/' + uuidA) && t.includes('voltou para a Administração') && t.includes('Administração: Bruno Admin'), 'sair volta para a empresa na Administração, com o histórico marcado');
  ok(sql(`SELECT fim IS NOT NULL FROM impersonacao_log WHERE organizador_id=${idA} ORDER BY id DESC LIMIT 1`) === '1', 'log da impersonação encerrado');
  await P.goto(BASE + '/painel');
  ok(!(await P.content()).includes('Você está navegando como') && (await P.locator('.conta-menu summary').innerText()).includes('BH Software'), 'depois de sair, o painel volta a ser o da própria empresa');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
