// Teste: "Vendas encerradas" no dia do evento (decisão do Bruno, 18/11/2026). Usa o produtor dup@a.test do fluxo-duplicar.js.
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-vendas-encerradas.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mariadb --default-character-set=utf8mb4 ffteste -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };

(async () => {
  const b = await chromium.launch();
  const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  P.on('dialog', (d) => d.accept());
  await P.goto(BASE + '/entrar');
  await P.fill('[name=email]', 'dup@a.test'); await P.fill('[name=senha]', 'Senha123');
  await Promise.all([P.waitForNavigation(), P.click('button[type=submit]')]);
  // Evento hoje, publicado, lote à venda
  sql("UPDATE evento SET status='publicado', publicado_em=UTC_TIMESTAMP(), data_inicio=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR), data_fim=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 4 HOUR) WHERE id=1");
  sql("DELETE FROM parametro_sistema WHERE chave='vendas_encerradas_evento_1'");
  const uuid = sql('SELECT uuid FROM evento WHERE id=1');
  const pub = BASE + '/e/' + uuid.replace(/-/g, '').slice(0, 10);

  await P.goto(BASE + '/painel/eventos/' + uuid);
  const btn = P.locator('.cabecalho__acoes button:has-text("Vendas abertas")');
  ok(await btn.isEnabled(), 'no dia do evento o botão "Vendas abertas" está habilitado');
  await Promise.all([P.waitForNavigation(), btn.click()]);
  ok(await P.isVisible('.cabecalho__acoes button:has-text("Vendas encerradas")'), 'após clicar mostra "Vendas encerradas"');
  await P.goto(pub);
  let t = await P.content();
  ok(t.includes('Vendas encerradas.') && !t.includes('Ingressos esgotados'), 'página pública diz "Vendas encerradas" (não "esgotados")');

  await P.goto(BASE + '/app/evento/' + uuid + '?aba=vendas');
  ok(await P.isVisible('button:has-text("Reabrir vendas")') && !(await P.isVisible('a:has-text("Vender ingressos")')), 'app: some "Vender ingressos" e aparece "Reabrir vendas"');
  await Promise.all([P.waitForNavigation(), P.click('button:has-text("Reabrir vendas")')]);
  ok(await P.isVisible('button:has-text("Encerrar vendas")') && await P.isVisible('a:has-text("Vender ingressos")'), 'app reabre as vendas');

  // Esgotado de verdade → "Ingressos esgotados"
  sql('UPDATE lote SET vendido=quantidade WHERE evento_id=1');
  await P.goto(pub);
  t = await P.content();
  ok(t.includes('Ingressos esgotados.'), 'com tudo vendido a página diz "Ingressos esgotados"');
  sql('UPDATE lote SET vendido=0 WHERE evento_id=1');

  // Fora do dia do evento: botão desabilitado
  sql("UPDATE evento SET data_inicio=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 DAY), data_fim=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 6 DAY) WHERE id=1");
  await P.goto(BASE + '/painel/eventos/' + uuid);
  ok(await P.locator('.cabecalho__acoes button:has-text("Vendas abertas")').isDisabled(), 'fora do dia do evento o botão fica desabilitado');
  await b.close();
})();
