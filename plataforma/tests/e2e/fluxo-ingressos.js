// Teste de ponta a ponta do cadastro de ingressos: gratuito/pago, por data/por lote,
// quem pode comprar e ativar/desativar (Playwright). Rode contra um banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-ingressos.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const codEv = (slug) => sql(`SELECT SUBSTRING(REPLACE(uuid,'-',''),1,10) FROM evento WHERE slug='${slug}'`);
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);
const lote = (nome) => sql(`SELECT CONCAT_WS('|', preco, modalidade, IFNULL(lote_anterior_id,'-'), visibilidade, status) FROM lote WHERE nome='${nome}'`);

(async () => {
  const b = await chromium.launch(); const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const erros = []; P.on('pageerror', (e) => erros.push(e.message));
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Ingressos'], ['email', 'bruno@ingressos.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Festa dos Lotes');
  await P.fill('[name=data_inicio]', local(20)); await P.fill('[name=data_fim]', local(21, '02:00'));
  await P.fill('[name="local[nome]"]', 'Estação Slod'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  await nav(P, P.click('button:has-text("Criar tipo")'));

  const abrir = async () => {
    if (await P.locator('details:has(> summary:has-text("Adicionar lote"))').getAttribute('open') === null) await P.click('summary:has-text("Adicionar lote")');
    return P.locator('form:has(input[name=_form][value^="lote-"])').last();
  };
  const salvar = async (f) => { await f.evaluate((el) => { el.noValidate = true; }); await nav(P, f.locator('button:has-text("lote")').click()); return P.content(); };

  // 1º lote: gratuito é o padrão, valor escondido; "Por lote" indisponível sem outro lote
  let f = await abrir();
  ok(await f.locator('input[name=cobranca][value=gratuito]').isChecked() && !(await f.locator('[name=preco]').isVisible()), 'novo lote: gratuito marcado e campo de valor escondido');
  await f.locator('input[name=cobranca][value=pago]').check({ force: true });
  ok(await f.locator('[name=preco]').isVisible(), 'marcar "Pago" mostra o campo de valor');
  ok(await f.locator('input[name=liberacao][value=por_lote]').isDisabled(), 'primeiro lote: "Por lote" desativado');
  await f.locator('[name=quantidade]').fill('100');
  ok((await salvar(f)).includes('Informe o valor do ingresso pago'), 'pago sem valor é recusado');
  f = await abrir();
  await f.locator('[name=nome]').fill('Primeiro'); await f.locator('[name=preco]').fill('40'); await f.locator('[name=quantidade]').fill('100');
  await f.locator('[name=modalidade]').selectOption('meia');
  ok((await salvar(f)).includes('Lote “Primeiro” criado'), 'lote pago criado');
  ok(lote('Primeiro') === '40.00|meia|-|publico|ativo', 'gravado: R$ 40, meia, por data, público, à venda');

  // 2º lote: por lote (padrão quando já existe lote), restrito a convidados
  f = await abrir();
  ok(await f.locator('input[name=liberacao][value=por_lote]').isChecked() && await f.locator('[name=lote_anterior_id]').isVisible()
    && !(await f.locator('[name=venda_inicio]').isVisible()), 'segundo lote: "Por lote" marcado, escolhe o anterior e esconde o início');
  await f.locator('[name=nome]').fill('Segundo'); await f.locator('[name=quantidade]').fill('50');
  await f.locator('[name=lote_anterior_id]').selectOption('');
  ok((await salvar(f)).includes('Escolha depois de qual lote'), 'por lote sem lote anterior é recusado');
  f = await abrir();
  await f.locator('[name=nome]').fill('Segundo'); await f.locator('[name=quantidade]').fill('50');
  await f.locator('[name=lote_anterior_id]').selectOption({ label: 'Primeiro' });
  await f.locator('input[name=visibilidade][value=convidados]').check();
  ok((await salvar(f)).includes('Lote “Segundo” criado'), 'lote por lote criado');
  const idPrimeiro = sql("SELECT id FROM lote WHERE nome='Primeiro'");
  ok(lote('Segundo') === `0.00|inteira|${idPrimeiro}|convidados|ativo`, 'gravado: gratuito, abre depois do Primeiro, só convidados');
  ok((await P.content()).includes('Após “Primeiro”'), 'lista mostra "Após “Primeiro”"');

  // 3º lote: adicionado manualmente, criado desativado
  f = await abrir();
  await f.locator('[name=nome]').fill('Equipe'); await f.locator('[name=quantidade]').fill('10');
  await f.locator('input[name=liberacao][value=por_data]').check({ force: true });
  await f.locator('input[name=visibilidade][value=oculto]').check();
  await f.locator('input[type=checkbox][name=ativo]').uncheck({ force: true });
  await salvar(f);
  ok(lote('Equipe') === '0.00|inteira|-|oculto|pausado', 'lote manual criado desativado');

  // ativar / desativar pela lista
  const linha = (n) => P.locator(`tr:not(.linha-acoes):has(td b:text-is("${n}"))`);
  ok((await linha('Equipe').innerText()).includes('Desativado'), 'lista mostra "Desativado"');
  await nav(P, linha('Primeiro').locator('button:has-text("Desativar")').click());
  ok(lote('Primeiro').endsWith('|pausado') && (await P.content()).includes('desativado: não aparece nem é vendido'), 'botão Desativar');
  await nav(P, linha('Primeiro').locator('button:has-text("Ativar")').click());
  ok(lote('Primeiro').endsWith('|ativo'), 'botão Ativar volta para à venda');

  // edição: o anterior não pode começar depois dele mesmo
  await linha('Primeiro').locator('button:has-text("Editar")').click();
  const fe = P.locator('tr.linha-acoes:visible form[data-lote-form]');
  await fe.locator('input[name=liberacao][value=por_lote]').check({ force: true });
  await fe.locator('[name=lote_anterior_id]').selectOption({ label: 'Segundo' });
  ok((await salvar(fe)).includes('Esse lote já começa depois deste'), 'recusa lotes que dependem um do outro');

  // página pública: só o público aparece; convidados e manual não
  ok((await P.content()).includes('Dados para o repasse cadastrados em Financeiro'), 'com lote pago, publicar pede conta de recebimento');
  sql("UPDATE lote SET preco=0 WHERE nome='Primeiro'"); // sem Mercado Pago ainda: publica como gratuito
  await P.reload();
  await nav(P, P.click('button:has-text("Publicar evento")'));
  const V = await (await b.newContext()).newPage();
  await V.goto(BASE + '/e/' + codEv('festa-dos-lotes'));
  const pub = await V.content();
  ok(pub.includes('Primeiro') && !pub.includes('Segundo') && !pub.includes('Equipe'), 'página do evento mostra só o lote para todo o público');
  ok(!pub.includes('abre quando o lote anterior encerrar'), 'lote seguinte da sequência não aparece antes da vez dele');

  // ocultar da página sem mexer na venda
  await P.goto(BASE + '/painel/eventos'); await nav(P, P.click('a:has-text("Festa dos Lotes")'));
  await nav(P, linha('Primeiro').locator('button:has-text("Ocultar")').click());
  ok(sql("SELECT CONCAT(exibir,'|',status) FROM lote WHERE nome='Primeiro'") === '0|ativo' && (await linha('Primeiro').innerText()).includes('Oculto da página'), 'Ocultar: some da página mas continua ativo');
  await V.goto(BASE + '/e/' + codEv('festa-dos-lotes'));
  ok(!(await V.content()).includes('Primeiro'), 'lote oculto não aparece na página do evento');
  await nav(P, linha('Primeiro').locator('button:has-text("Exibir")').click());
  await V.reload();
  ok((await V.content()).includes('Primeiro'), 'Exibir: volta a aparecer');

  // mudar a data do evento: o fim das vendas dos lotes acompanha o novo término
  const fimAntes = sql("SELECT data_fim FROM evento WHERE slug='festa-dos-lotes'");
  await P.goto(BASE + '/painel/eventos'); await nav(P, P.click('a:has-text("Festa dos Lotes")'));
  await nav(P, P.click('a:has-text("Editar")'));
  await P.fill('[name=data_inicio]', local(30)); await P.fill('[name=data_fim]', local(31, '03:00'));
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  const fimNovo = sql("SELECT data_fim FROM evento WHERE slug='festa-dos-lotes'");
  ok(fimNovo !== fimAntes && sql(`SELECT COUNT(*) FROM lote l JOIN evento e ON e.id=l.evento_id WHERE e.slug='festa-dos-lotes' AND l.venda_fim<>'${fimNovo}'`) === '0',
    'mudar a data do evento leva o fim das vendas de todos os lotes para o novo término');
  ok(sql("SELECT COUNT(*) FROM auditoria WHERE acao='lote.fim_vendas_ajustado'") === '3', 'ajuste registrado na auditoria de cada lote');
  // novo lote já vem com o fim das vendas no término do evento
  await P.click('summary:has-text("Adicionar lote")');
  const fn = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  ok((await fn.locator('[name=venda_fim]').inputValue()).endsWith('T03:00'), 'novo lote sugere o fim das vendas no término do evento');

  // lote que passa do fim do evento (dado antigo) é corrigido ao salvar o evento, mesmo sem mudar a data
  sql("UPDATE lote SET venda_fim=DATE_ADD(venda_fim, INTERVAL 5 DAY) WHERE nome='Equipe'");
  await P.goto(BASE + '/painel');
  ok(await P.locator('tr:has-text("Festa dos Lotes") a:has-text("Editar")').count() === 1, 'Próximos eventos tem o botão Editar');
  await nav(P, P.click('tr:has-text("Festa dos Lotes") a:has-text("Editar")'));
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  ok(sql("SELECT COUNT(*) FROM lote l JOIN evento e ON e.id=l.evento_id WHERE l.venda_fim>e.data_fim") === '0', 'salvar o evento corrige lote que vendia depois do fim');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
