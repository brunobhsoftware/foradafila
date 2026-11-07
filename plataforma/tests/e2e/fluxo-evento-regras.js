// Teste de ponta a ponta: capacidade opcional (antes e depois do V005), declaração da política
// de cancelamento e "Mostrar eventos passados" em Preferências (Playwright). Requer o V004.
// ATENÇÃO: altera a coluna evento.capacidade do banco local para simular o antes/depois do V005.
// Rode contra um banco limpo:  BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-evento-regras.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const V005 = __dirname + '/../../../banco/V005__evento_capacidade_opcional.sql';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

(async () => {
  const b = await chromium.launch(); const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const erros = []; P.on('pageerror', (e) => erros.push(e.message));
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Regras'], ['email', 'bruno@regras.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));

  const novoEvento = async (titulo, capacidade, declarar) => {
    await P.goto(BASE + '/painel/eventos/novo');
    await P.evaluate(() => { document.querySelector('form[enctype]').noValidate = true; });
    await P.fill('[name=titulo]', titulo);
    if (capacidade !== null) await P.fill('[name=capacidade]', String(capacidade));
    await P.fill('[name=data_inicio]', local(12)); await P.fill('[name=data_fim]', local(13, '02:00'));
    await P.fill('[name="local[nome]"]', 'Estação Slod'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
    if (declarar) await P.check('[name=aceite_cancelamento]');
    await nav(P, P.click('.acoes-form button[type=submit]'));
    return P.content();
  };

  // ---- antes do V005: capacidade continua obrigatória ----
  sql('ALTER TABLE evento MODIFY COLUMN capacidade INT UNSIGNED NOT NULL');
  await P.goto(BASE + '/painel/eventos/novo');
  ok((await P.content()).includes('Ficará opcional após a atualização V005'), 'sem o V005, a tela avisa que a capacidade ainda é obrigatória');
  ok((await novoEvento('Sem V005', null, true)).includes('O campo capacidade é obrigatório'), 'sem o V005, evento sem capacidade é recusado (sem erro 500)');

  // ---- depois do V005 ----
  execSync(`mysql -uroot foradafila < ${V005}`);
  await P.goto(BASE + '/painel/eventos/novo');
  const form = await P.content();
  ok(form.includes('Opcional. Em branco, você cadastra quantos ingressos quiser') && form.includes('é responsabilidade do produtor (organizador) devolver todos os valores'), 'com o V005, capacidade opcional e termo de cancelamento na tela');
  ok((await novoEvento('Sem declaracao', null, false)).includes('marque a declaração sobre a política de cancelamento'), 'sem marcar a declaração, o evento não é salvo');
  ok((await novoEvento('Festa Livre', null, true)).includes('Evento criado'), 'evento criado sem capacidade máxima');
  ok(sql("SELECT IFNULL(capacidade,'NULL') FROM evento WHERE slug='festa-livre'") === 'NULL', 'capacidade gravada como vazia');
  const aud = sql("SELECT CONCAT(ip, '|', JSON_UNQUOTE(JSON_EXTRACT(depois, '$.texto'))) FROM auditoria WHERE acao='evento.declaracao_cancelamento' ORDER BY id DESC LIMIT 1");
  ok(aud.startsWith('127.0.0.1|Declaro que, em caso de cancelamento'), 'declaração registrada com IP e texto');
  ok((await P.content()).includes('sem capacidade máxima'), 'painel mostra "sem capacidade máxima"');

  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  let f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('[name=preco]').fill('0'); await f.locator('[name=quantidade]').fill('5000');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  ok((await P.content()).includes('Lote “1º lote” criado'), 'sem capacidade, aceita lote de 5.000 ingressos');
  ok((await P.content()).includes('Sem capacidade máxima definida'), 'lista "Para publicar" explica a falta de capacidade');
  await nav(P, P.click('button:has-text("Publicar evento")'));
  ok((await P.content()).includes('Evento publicado'), 'evento sem capacidade é publicado (trigger aceita)');

  const V = await (await b.newContext()).newPage();
  await V.goto(BASE + '/e/festa-livre');
  ok((await V.content()).includes('é responsabilidade do produtor (Estacao Slod Ltda) devolver todos os valores pagos aos clientes'), 'página pública mostra a política de cancelamento');
  const slugOrg = sql("SELECT slug FROM organizador WHERE documento='41902878000108'");
  await V.goto(BASE + '/p/' + slugOrg);
  const card = V.locator('.pub-evento-card:has-text("Festa Livre")');
  ok(await card.count() === 1 && (await card.locator('.pub-evento-card__preco').innerText()) === 'Gratuito', 'página de eventos da empresa: cartão com preço "Gratuito"');

  // com capacidade, a soma dos lotes continua limitada
  await novoEvento('Com Limite', 100, true);
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('[name=preco]').fill('0'); await f.locator('[name=quantidade]').fill('150');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  ok((await P.content()).includes('passa da capacidade'), 'com capacidade 100, lote de 150 é recusado');

  // "Mostrar eventos passados" em Preferências (mesma opção da Organização)
  await P.goto(BASE + '/painel/preferencias');
  ok((await P.content()).includes('Mostrar eventos passados'), 'opção aparece em Preferências');
  await P.check('input[type=checkbox][name=mostrar_eventos_passados]');
  await nav(P, P.click('.acoes-form button'));
  ok(sql("SELECT mostrar_eventos_passados FROM organizador WHERE documento='41902878000108'") === '1', 'marcar em Preferências grava na organização');
  await P.goto(BASE + '/painel/organizacao');
  ok(await P.isChecked('input[type=checkbox][name=mostrar_eventos_passados]'), 'Organização mostra a mesma opção marcada');
  await P.goto(BASE + '/painel/preferencias');
  await P.uncheck('input[type=checkbox][name=mostrar_eventos_passados]');
  await nav(P, P.click('.acoes-form button'));
  ok(sql("SELECT mostrar_eventos_passados FROM organizador WHERE documento='41902878000108'") === '0', 'desmarcar em Preferências desliga');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
