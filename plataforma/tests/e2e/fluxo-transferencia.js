// Teste: transferência de titularidade em Meus ingressos + duração do evento + sem reentrada (decisões do Bruno, 18/11/2026).
// Usa o produtor dup@a.test e o evento 1 do fluxo-duplicar.js.   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-transferencia.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mariadb --default-character-set=utf8mb4 ffteste -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };

(async () => {
  // Evento daqui a 5 dias, com transferência até 24 h antes; um comprador com 1 ingresso válido.
  sql("UPDATE evento SET status='publicado', permite_transferencia=1, transferencia_ate_h=24, data_inicio=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 DAY), data_fim=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 DAY)+INTERVAL 150 MINUTE WHERE id=1");
  const hash = execSync(`php -r 'echo password_hash("Senha123", PASSWORD_BCRYPT);'`).toString().replace(/\$/g, '\\$');
  sql("DELETE t FROM ingresso_transferencia t JOIN ingresso i ON i.id=t.ingresso_origem_id WHERE i.evento_id=1");
  sql("DELETE p FROM participante p WHERE p.evento_id=1"); sql("DELETE FROM ingresso WHERE evento_id=1"); sql("DELETE FROM pedido_item WHERE pedido_id IN (SELECT id FROM pedido WHERE evento_id=1)"); sql("DELETE FROM pedido WHERE evento_id=1");
  sql(`DELETE FROM comprador WHERE email='comp@a.test'`);
  sql(`INSERT INTO comprador (uuid,nome,email,senha_hash,email_verificado_em) VALUES (UUID(),'Carla Compradora','comp@a.test','${hash}',UTC_TIMESTAMP())`);
  const comp = sql("SELECT id FROM comprador WHERE email='comp@a.test'");
  const org = sql('SELECT organizador_id FROM evento WHERE id=1');
  const lote = sql('SELECT id FROM lote WHERE evento_id=1 ORDER BY id LIMIT 1');
  const tipo = sql(`SELECT ingresso_tipo_id FROM lote WHERE id=${lote}`);
  sql(`INSERT INTO pedido (uuid,codigo,organizador_id,evento_id,comprador_id,chave_idempotencia,valor_bruto,valor_total,expira_em,status,pago_em) VALUES (UUID(),'TST0001',${org},1,${comp},UUID(),0,0,UTC_TIMESTAMP(),'pago',UTC_TIMESTAMP())`);
  const ped = sql("SELECT id FROM pedido WHERE codigo='TST0001'");
  sql(`INSERT INTO pedido_item (pedido_id,lote_id,quantidade,preco_unitario,subtotal) VALUES (${ped},${lote},1,0,0)`);
  const item = sql(`SELECT id FROM pedido_item WHERE pedido_id=${ped}`);
  sql(`INSERT INTO ingresso (uuid,organizador_id,evento_id,pedido_id,pedido_item_id,lote_id,ingresso_tipo_id,numero,codigo_qr,valor_pago,status) VALUES (UUID(),${org},1,${ped},${item},${lote},${tipo},1,'codigoantigo0000000000000000000000000000000',0,'valido')`);
  const ing = sql(`SELECT id FROM ingresso WHERE pedido_id=${ped}`);
  sql(`INSERT INTO participante (ingresso_id,organizador_id,evento_id,nome,email) VALUES (${ing},${org},1,'Carla Compradora','comp@a.test')`);

  const b = await chromium.launch();
  const P = await (await b.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  P.on('dialog', (d) => d.accept());
  await P.goto(BASE + '/minha-conta/entrar');
  await P.fill('[name=email]', 'comp@a.test');
  await Promise.all([P.waitForNavigation(), P.click('form.pub-cartao button')]);
  if (await P.locator('[name=senha]').count()) { await P.fill('[name=senha]', 'Senha123'); await Promise.all([P.waitForNavigation(), P.click('form button.botao--largo, form button[type=submit]')]); }
  await P.goto(BASE + '/minha-conta');
  ok(await P.isVisible('button:has-text("Transferir")'), 'Meus ingressos mostra "Transferir"');
  await P.click('button:has-text("Transferir")');
  await P.fill('[name=para_nome]', 'joão da silva');
  await P.fill('[name=para_email]', 'Joao@B.test');
  await P.check('[name=confirmo]');
  await Promise.all([P.waitForNavigation(), P.click('button:has-text("Transferir ingresso")')]);
  ok((await P.content()).includes('transferido para'), 'mensagem de transferência feita');
  ok(sql(`SELECT CONCAT(nome,'|',email) FROM participante WHERE ingresso_id=${ing}`) === 'João da Silva|joao@b.test', 'novo titular gravado (nome padronizado)');
  ok(sql(`SELECT codigo_qr FROM ingresso WHERE id=${ing}`) !== 'codigoantigo0000000000000000000000000000000', 'QR Code antigo deixou de valer (código novo)');
  ok(sql(`SELECT COUNT(*) FROM mensagem_envio WHERE tipo='transferencia' AND destinatario='joao@b.test'`) === '1', 'novo titular recebe o e-mail');
  await P.goto(BASE + '/minha-conta');
  ok(!(await P.isVisible('button:has-text("Transferir")')), 'não transfere duas vezes');

  // Produtor: duração e sem reentrada
  const Q = await (await b.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  await Q.goto(BASE + '/entrar'); await Q.fill('[name=email]', 'dup@a.test'); await Q.fill('[name=senha]', 'Senha123');
  await Promise.all([Q.waitForNavigation(), Q.click('button[type=submit]')]);
  await Q.goto(BASE + '/painel/eventos/' + sql('SELECT uuid FROM evento WHERE id=1') + '/editar');
  await Q.click('[data-bloco]:has-text("Data e horário") [data-bloco-editar]');
  ok((await Q.textContent('[data-duracao-evento]')).includes('2 horas e 30 min'), 'mostra "O seu evento irá durar 2 horas e 30 min"');
  ok(!(await Q.content()).includes('reentrada'), 'a opção de reentrada sumiu do cadastro');
  await b.close();
})();
