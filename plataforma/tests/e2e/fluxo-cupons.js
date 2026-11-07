// Teste de ponta a ponta de Cupons: criar (percentual/valor fixo, evento, lotes, validade, limites), recusas,
// editar, ativar/desativar, filtro por evento e cálculo do desconto. Banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-cupons.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

(async () => {
  const b = await chromium.launch(); const erros = [];
  const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage(); P.on('pageerror', (e) => erros.push(e.message));
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Cupons'], ['email', 'bruno@cupons.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));
  for (const titulo of ['Festa A', 'Festa B']) {
    await P.goto(BASE + '/painel/eventos/novo');
    await P.fill('[name=titulo]', titulo);
    await P.fill('[name=data_inicio]', local(20)); await P.fill('[name=data_fim]', local(21, '02:00'));
    await P.fill('[name="local[nome]"]', 'Local'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
    await P.check('[name=aceite_cancelamento]');
    await nav(P, P.click('.acoes-form button[type=submit]'));
    await nav(P, P.click('button:has-text("Criar tipo")'));
    for (const [nome, preco] of [['Pista', '100'], ['VIP', '200']]) {
      await P.click('summary:has-text("Adicionar lote")');
      const f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
      await f.locator('[name=nome]').fill(nome); await f.locator('[name=quantidade]').fill('50');
      await f.locator('input[name=cobranca][value=pago]').check({ force: true }); await f.locator('[name=preco]').fill(preco);
      if (await f.locator('input[name=liberacao][value=por_data]').isEnabled()) await f.locator('input[name=liberacao][value=por_data]').check({ force: true });
      await nav(P, f.locator('button:has-text("Adicionar lote")').click());
    }
  }

  await nav(P, P.click('.menu-lateral a:has-text("Cupons")'));
  ok((await P.content()).includes('Nenhum cupom ainda'), 'tela de cupons vazia');
  const novo = P.locator('form:has(input[name=_cupom][value=novo])');
  ok(await novo.locator('[data-sem-evento]').isVisible(), 'sem evento escolhido: explica que dá para limitar lotes');

  // percentual > 100 é recusado
  await novo.locator('[name=codigo]').fill('amigos 10'); await novo.locator('[name=valor]').fill('150');
  await nav(P, novo.locator('button:has-text("Criar cupom")').click());
  ok((await P.content()).includes('Percentual de no máximo 100%'), 'percentual acima de 100% é recusado');

  // cria cupom de 10% só para o VIP da Festa A
  await novo.locator('[name=codigo]').fill('amigos 10'); await novo.locator('[name=valor]').fill('10');
  await novo.locator('[name=evento]').selectOption({ label: 'Festa A' });
  ok(await novo.locator('[data-lotes-evento]:visible').count() === 1 && (await novo.locator('[data-lotes-evento]:visible').innerText()).includes('VIP'), 'escolher o evento mostra os lotes dele');
  await novo.locator('[data-lotes-evento]:visible label:has-text("VIP") input').check();
  await novo.locator('[name=max_usos]').fill('50'); await novo.locator('[name=descricao]').fill('Instagram');
  await nav(P, novo.locator('button:has-text("Criar cupom")').click());
  let t = await P.content();
  ok(t.includes('Cupom AMIGOS10 criado') && t.includes('10%') && t.includes('Festa A') && t.includes('0 / 50') && t.includes('Ativo'), 'cupom criado (código em maiúsculas, sem espaço)');
  ok(sql("SELECT COUNT(*) FROM cupom_lote cl JOIN lote l ON l.id=cl.lote_id WHERE l.nome='VIP'") === '1', 'cupom vinculado só ao lote VIP');

  // código repetido
  await novo.locator('[name=codigo]').fill('AMIGOS10'); await novo.locator('[name=valor]').fill('5');
  await nav(P, novo.locator('button:has-text("Criar cupom")').click());
  ok((await P.content()).includes('Já existe um cupom com esse código'), 'código repetido é recusado');

  // valor fixo para todos os eventos, com validade
  await novo.locator('[name=codigo]').fill('VALE20'); await novo.locator('[name=tipo_desconto]').selectOption('valor_fixo'); await novo.locator('[name=valor]').fill('20');
  await novo.locator('[name=evento]').selectOption('');
  await novo.locator('[name=valido_de]').fill(local(5, '10:00')); await novo.locator('[name=valido_ate]').fill(local(2, '10:00'));
  await nav(P, novo.locator('button:has-text("Criar cupom")').click());
  ok((await P.content()).includes('fim da validade precisa ser depois'), 'validade invertida é recusada');
  await novo.locator('[name=valido_ate]').fill(local(10, '10:00'));
  await nav(P, novo.locator('button:has-text("Criar cupom")').click());
  t = await P.content();
  ok(t.includes('Cupom VALE20 criado') && t.includes('R$ 20,00') && t.includes('Todos os eventos') && t.includes('Agendado'), 'cupom de valor fixo, todos os eventos, agendado');

  // filtro por evento: Festa B mostra o de todos os eventos, não o da Festa A
  await nav(P, P.selectOption('select[name=evento]:not([data-cupom-evento])', { label: 'Festa B' }));
  t = await P.locator('table.tabela tbody').innerText();
  ok(t.includes('VALE20') && !t.includes('AMIGOS10'), 'filtro por evento (inclui cupons de todos os eventos)');
  await P.goto(BASE + '/painel/cupons');

  // editar: limite menor que os usos é recusado; editar ok
  sql("UPDATE cupom SET usos=3 WHERE codigo='AMIGOS10'");
  await P.reload();
  const linha = P.locator('tr:has-text("AMIGOS10")').first();
  await linha.locator('button:has-text("Editar")').click();
  const fe = P.locator('tr.linha-acoes:visible form');
  await fe.locator('[name=max_usos]').fill('2');
  await nav(P, fe.locator('button:has-text("Salvar cupom")').click());
  ok((await P.content()).includes('já foi usado 3 vez(es)'), 'limite menor que os usos já feitos é recusado');
  const fe2 = P.locator('tr.linha-acoes:visible form');
  await fe2.locator('[name=max_usos]').fill('3');
  await nav(P, fe2.locator('button:has-text("Salvar cupom")').click());
  ok((await P.content()).includes('AMIGOS10 atualizado') && (await P.content()).includes('Esgotado'), 'limite igual aos usos: cupom aparece como Esgotado');

  // desativar / ativar
  await nav(P, P.locator('tr:has-text("VALE20")').first().locator('button:has-text("Desativar")').click());
  ok((await P.content()).includes('VALE20 desativado') && sql("SELECT ativo FROM cupom WHERE codigo='VALE20'") === '0', 'desativar cupom');
  await nav(P, P.locator('tr:has-text("VALE20")').first().locator('button:has-text("Ativar")').click());
  ok(sql("SELECT ativo FROM cupom WHERE codigo='VALE20'") === '1', 'ativar de novo');
  ok(sql("SELECT COUNT(*) FROM auditoria WHERE acao LIKE 'cupom.%'") >= '5', 'ações de cupom na auditoria');

  // cálculo do desconto (usado pela venda paga)
  sql("UPDATE cupom SET usos=0, max_usos=50 WHERE codigo='AMIGOS10'");
  const vip = sql("SELECT l.id FROM lote l JOIN evento e ON e.id=l.evento_id WHERE l.nome='VIP' AND e.titulo='Festa A'");
  const pista = sql("SELECT l.id FROM lote l JOIN evento e ON e.id=l.evento_id WHERE l.nome='Pista' AND e.titulo='Festa A'");
  const php = (cod) => execSync(`php artisan tinker --execute='${cod}'`, { cwd: __dirname + '/../..' }).toString().trim();
  const r = php(`$c = App\\Models\\Cupom::withoutGlobalScopes()->where("codigo","AMIGOS10")->first(); echo json_encode([$c->desconto(200, ${vip}), $c->desconto(100, ${pista})]);`);
  ok(r.endsWith('[20,null]'), 'desconto: 10% no VIP (R$ 20) e não vale na Pista ' + r);

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
