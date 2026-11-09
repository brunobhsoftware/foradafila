// Teste de ponta a ponta do Check-in pelo navegador: número do ingresso, QR assinado, assinatura falsa,
// ingresso já usado/cancelado, busca manual, contadores, últimas leituras e desfazer. Banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-checkin.js
const { chromium } = require('playwright');
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
  for (const [n, v] of [['nome', 'Bruno Portaria'], ['email', 'bruno@portaria.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));
  formularioLegado(sql);
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Show da Portaria');
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
  await V.goto(BASE + '/e/' + codEv('show-da-portaria'));
  await V.selectOption('select[name^="q["]', '3');
  await nav(V, V.click('button:has-text("Garantir ingresso")'));
  await V.fill('[name=cpf]', '99603082430'); await V.waitForSelector('[name=nome]:visible'); await V.fill('[name=nome]', 'Ana Compradora'); await V.fill('[name=email]', 'ana@publico.test');
  for (const [i, nome, cpf] of [[0, 'Ana Compradora', '529.982.247-25'], [1, 'Pedro Amigo', '153.509.460-56'], [2, 'Carla Prima', '111.444.777-35']]) {
    await V.fill(`[name="participantes[${i}][nome]"]`, nome); await V.fill(`[name="participantes[${i}][cpf]"]`, cpf);
  }
  await V.check('[name=aceite_termos]');
  await nav(V, V.click('button:has-text("Confirmar inscrição")'));
  const idEv = sql("SELECT id FROM evento WHERE slug='show-da-portaria'");
  sql(`UPDATE evento SET abertura_portoes=NULL WHERE id=${idEv}`); // o formulário preenche os portões com o início do evento
  // check-in só no dia do evento: o evento passa a estar acontecendo agora
  sql(`UPDATE evento SET data_inicio = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE), data_fim = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 4 HOUR) WHERE id=${idEv}`);
  const ing = (nome, campo) => sql(`SELECT i.${campo} FROM ingresso i JOIN participante p ON p.ingresso_id=i.id WHERE p.nome='${nome}'`);

  // ---- tela ----
  await nav(P, P.click('.menu-lateral a:has-text("Check-in")'));
  ok((await P.content()).includes('Show da Portaria') && (await P.locator('[data-n=total]').innerText()) === '3', 'tela do check-in com 3 ingressos válidos');
  const resultado = async () => { await P.waitForFunction(() => !document.querySelector('[data-resultado]').hidden); const t = await P.locator('[data-resultado]').innerText(); await P.evaluate(() => { document.querySelector('[data-resultado]').hidden = true; }); return t; };
  const validar = async (codigo) => { await P.fill('#checkin-codigo', codigo); await P.press('#checkin-codigo', 'Enter'); return resultado(); };

  // número do ingresso (como um leitor digitando + Enter)
  let r = await validar(`FF-${String(idEv).padStart(4, '0')}-${String(ing('Ana Compradora', 'numero')).padStart(6, '0')}`);
  ok(r.includes('Entrada liberada') && r.includes('Ana Compradora'), 'número do ingresso: entrada liberada');
  ok((await P.locator('[data-n=entraram]').innerText()) === '1' && (await P.locator('[data-n=pct]').innerText()) === '33', 'contadores atualizam (1 de 3, 33%)');
  r = await validar(String(ing('Ana Compradora', 'numero')));
  ok(r.includes('já utilizado') && r.includes('entrou'), 'segunda leitura: já utilizado, com a hora da entrada');

  // QR assinado
  r = await validar(qr(ing('Pedro Amigo', 'codigo_qr')));
  ok(r.includes('Entrada liberada') && r.includes('Pedro Amigo'), 'QR Code assinado: entrada liberada');
  r = await validar(`FF1.${ing('Carla Prima', 'codigo_qr')}.0000000000000000`);
  ok(r.includes('assinatura não confere'), 'QR com assinatura falsa é recusado');
  r = await validar('qualquer coisa');
  ok(r.includes('não encontrado'), 'código desconhecido: não encontrado');
  r = await validar(`FF-9999-000001`);
  ok(r.includes('outro evento'), 'número de outro evento é recusado');

  // cancelado
  sql(`UPDATE ingresso i JOIN participante p ON p.ingresso_id=i.id SET i.status='cancelado' WHERE p.nome='Carla Prima'`);
  r = await validar(qr(ing('Carla Prima', 'codigo_qr')));
  ok(r.includes('cancelado'), 'ingresso cancelado é recusado');
  sql(`UPDATE ingresso i JOIN participante p ON p.ingresso_id=i.id SET i.status='valido' WHERE p.nome='Carla Prima'`);

  // busca manual
  await P.fill('[data-busca]', 'carla');
  await P.waitForSelector('.checkin__item');
  ok((await P.locator('.checkin__item').innerText()).includes('Carla Prima'), 'busca manual por nome');
  await P.click('.checkin__item button:has-text("Registrar entrada")');
  r = await resultado();
  ok(r.includes('Entrada liberada') && r.includes('Carla Prima') && (await P.locator('[data-n=entraram]').innerText()) === '3', 'entrada pela busca manual; 3 de 3');
  await P.waitForFunction(() => document.querySelector('.checkin__item button')?.disabled === true);
  ok(true, 'depois da entrada, o botão da busca fica desativado');

  // log, últimas leituras, desfazer
  ok(sql(`SELECT COUNT(*) FROM checkin_log WHERE evento_id=${idEv}`) === '8' && sql(`SELECT COUNT(*) FROM checkin_log WHERE evento_id=${idEv} AND resultado='aceito'`) === '3', 'todas as leituras ficam no log (8, 3 aceitas)');
  ok(sql(`SELECT GROUP_CONCAT(DISTINCT metodo ORDER BY CAST(metodo AS CHAR)) FROM checkin_log WHERE resultado='aceito'`) === 'busca_manual,numero_ingresso,qr_code', 'método registrado (número, QR, busca)');
  await P.reload();
  const tabela = await P.locator('section:has-text("Últimas leituras") table').innerText();
  ok(tabela.includes('Entrada liberada') && tabela.includes('assinatura não confere') && tabela.includes('Bruno Portaria'), 'últimas leituras com resultado e operador');
  P.once('dialog', (d) => d.accept());
  await nav(P, P.locator('section:has-text("Últimas leituras") button:has-text("Desfazer")').first().click());
  ok((await P.content()).includes('desfeito') && sql(`SELECT COUNT(*) FROM ingresso WHERE evento_id=${idEv} AND status='utilizado'`) === '2', 'desfazer check-in volta o ingresso para válido');

  // portões fechados
  sql(`UPDATE evento SET data_inicio=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 HOUR), abertura_portoes=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 HOUR) WHERE id=${idEv}`);
  await P.reload();
  const livre = sql(`SELECT numero FROM ingresso WHERE evento_id=${idEv} AND status='valido' LIMIT 1`);
  r = await validar(livre);
  ok(r.includes('O check-in abre em'), 'antes da abertura dos portões, a entrada é recusada (mostra quando abre)');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
