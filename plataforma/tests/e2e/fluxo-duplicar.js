// Teste de ponta a ponta: Duplicar evento (decisão do Bruno, 17/11/2026). Banco limpo: BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-duplicar.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mariadb --default-character-set=utf8mb4 ffteste -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };

(async () => {
  const b = await chromium.launch();
  const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  await P.goto(BASE + '/cadastro');
  await P.fill('[name=nome]', 'Bruno Teste');
  await P.fill('[name=email]', 'dup@a.test');
  await P.fill('[name=telefone]', '11987654321');
  await P.fill('[name=senha]', 'Senha123');
  await P.fill('[name=senha_confirmation]', 'Senha123');
  await P.check('[name=tipo_pessoa][value=PJ]', { force: true });
  await P.fill('[name=documento]', '11.222.333/0001-81');
  await P.fill('[name=razao_social]', 'Choperia X Ltda');
  await P.fill('[name=nome_fantasia]', 'Choperia X');
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await Promise.all([P.waitForNavigation(), P.click('button[type=submit]')]);
  const org = sql("SELECT id FROM organizador ORDER BY id DESC LIMIT 1");
  ok(!!org, 'organização criada (' + org + ')');

  sql(`INSERT INTO local (organizador_id,nome,cidade,uf) VALUES (${org},'Casa X','São Paulo','SP')`);
  const loc = sql('SELECT MAX(id) FROM local');
  sql(`INSERT INTO evento (uuid,organizador_id,local_id,titulo,slug,data_inicio,data_fim,status) VALUES (UUID(),${org},${loc},'Show de Teste','show-de-teste',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 11 DAY),'rascunho')`);
  const ev = sql('SELECT MAX(id) FROM evento');
  sql(`INSERT INTO ingresso_tipo (organizador_id,evento_id,nome) VALUES (${org},${ev},'Pista')`);
  const tp = sql('SELECT MAX(id) FROM ingresso_tipo');
  sql(`INSERT INTO lote (uuid,organizador_id,evento_id,ingresso_tipo_id,nome,preco,quantidade,venda_inicio,venda_fim,vendido) VALUES (UUID(),${org},${ev},${tp},'1º lote',0,100,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 9 DAY),7)`);
  const l1 = sql('SELECT MAX(id) FROM lote');
  sql(`INSERT INTO lote (uuid,organizador_id,evento_id,ingresso_tipo_id,lote_anterior_id,nome,preco,quantidade,venda_inicio,venda_fim) VALUES (UUID(),${org},${ev},${tp},${l1},'2º lote',0,50,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 9 DAY))`);
  const uuid = sql(`SELECT uuid FROM evento WHERE id=${ev}`);

  await P.goto(BASE + '/painel/eventos');
  ok(await P.isVisible('button:has-text("Duplicar")'), 'Meus eventos mostra o botão Duplicar');
  await P.goto(BASE + '/painel/eventos/' + uuid);
  ok(await P.isVisible('.cabecalho__acoes button:has-text("Duplicar")'), 'página do evento mostra o botão Duplicar');
  P.on('dialog', (d) => d.accept());
  await Promise.all([P.waitForNavigation(), P.click('.cabecalho__acoes button:has-text("Duplicar")')]);
  ok(P.url().includes('/editar'), 'abre a edição da cópia (' + P.url() + ')');
  const novo = sql(`SELECT id FROM evento WHERE id<>${ev} ORDER BY id DESC LIMIT 1`);
  ok(sql(`SELECT CONCAT(titulo,'|',status,'|',IFNULL(local_id,0)<>${loc}) FROM evento WHERE id=${novo}`) === 'Show de Teste (cópia)|rascunho|1', 'cópia em rascunho, com título "(cópia)" e local próprio');
  ok(sql(`SELECT COUNT(*) FROM ingresso_tipo WHERE evento_id=${novo}`) === '1' && sql(`SELECT COUNT(*) FROM lote WHERE evento_id=${novo}`) === '2', 'tipos e lotes copiados');
  ok(sql(`SELECT SUM(vendido)+SUM(reservado) FROM lote WHERE evento_id=${novo}`) === '0', 'cópia sem vendas');
  ok(sql(`SELECT COUNT(*) FROM lote a JOIN lote b ON b.id=a.lote_anterior_id WHERE a.evento_id=${novo} AND b.evento_id=${novo}`) === '1', '"Por lote" aponta para o lote da cópia');
  ok((await P.content()).includes('Evento duplicado'), 'mensagem de sucesso');
  await b.close();
})();
