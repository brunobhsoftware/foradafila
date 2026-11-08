// Teste de ponta a ponta de "quem paga a taxa" (V010): padrão na Organização, escolha por evento,
// cálculo com o motor de taxas (5% com mínimo de R$ 1), preço na página do evento e no painel, textos do site. Banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-taxa.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

(async () => {
  // taxa padrão da plataforma: 5% com mínimo de R$ 1,00
  sql("UPDATE taxa_regra SET percentual=5, valor_fixo=0, valor_minimo=1 WHERE operacao='ingresso_pago'");
  const b = await chromium.launch(); const erros = [];
  const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage(); P.on('pageerror', (e) => erros.push(e.message));
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Taxa'], ['email', 'bruno@taxa.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));

  // ---- Organização: padrão ----
  await P.goto(BASE + '/painel/organizacao');
  let t = await P.locator('section:has(h2:text("Taxa da plataforma"))').innerText();
  ok(t.includes('Taxa embutida (eu absorvo)') && t.includes('você recebe R$ 47,50') && t.includes('comprador paga R$ 52,50'), 'Organização explica as duas opções com exemplo (R$ 50 → taxa R$ 2,50)');
  ok(await P.isChecked('input[name=taxa_comprador][value="0"]'), 'padrão inicial: taxa embutida');
  await P.fill('[name=representante_nome]', 'Bruno Taxa'); await P.fill('[name=representante_cpf]', '529.982.247-25');
  await P.check('input[name=taxa_comprador][value="1"]', { force: true });
  await nav(P, P.click('form[action$="/painel/organizacao"] .acoes-form button'));
  ok(sql("SELECT taxa_comprador FROM organizador") === '1', 'organização passa a repassar a taxa ao comprador');

  // ---- Evento com lotes pagos ----
  await P.goto(BASE + '/painel/eventos/novo');
  ok((await P.locator('#c_taxa_comprador option').first().innerText()).includes('Padrão da organização — repassada ao comprador'), 'evento mostra o padrão da organização');
  await P.fill('[name=titulo]', 'Show com Taxa');
  await P.fill('[name=data_inicio]', local(15)); await P.fill('[name=data_fim]', local(16, '02:00'));
  await P.fill('[name="local[nome]"]', 'Local'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  ok(sql("SELECT IFNULL(taxa_comprador,'padrão') FROM evento") === 'padrão', 'evento usa o padrão (NULL)');
  await nav(P, P.click('button:has-text("Criar tipo")'));
  for (const [nome, preco] of [['Barato', '5'], ['Pista', '100']]) {
    await P.click('summary:has-text("Adicionar lote")');
    const f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
    await f.locator('[name=nome]').fill(nome); await f.locator('[name=quantidade]').fill('50');
    await f.locator('input[name=cobranca][value=pago]').check({ force: true }); await f.locator('[name=preco]').fill(preco);
    if (await f.locator('input[name=liberacao][value=por_data]').isEnabled()) await f.locator('input[name=liberacao][value=por_data]').check({ force: true });
    await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  }
  const precoLinha = async (n) => (await P.locator(`tr:not(.linha-acoes):has(td b:text-is("${n}"))`).locator('td').nth(2).innerText()).replace(/\s+/g, ' ');
  ok((await precoLinha('Barato')).includes('comprador paga R$ 6,00') && (await precoLinha('Pista')).includes('comprador paga R$ 105,00'),
    'painel: repassada → comprador paga (R$ 5 + mínimo R$ 1; R$ 100 + 5%)');

  // publica (conta de recebimento ativa simulada)
  const org = sql('SELECT id FROM organizador');
  sql(`INSERT INTO conta_pagamento (uuid, organizador_id, gateway_id, apelido, ambiente, status) VALUES (UUID(), ${org}, (SELECT id FROM gateway WHERE codigo='mercadopago'), 'MP', 'producao', 'ativa')`);
  sql("UPDATE evento SET conta_pagamento_id=(SELECT id FROM conta_pagamento LIMIT 1)");
  await P.reload();
  await nav(P, P.click('button:has-text("Publicar evento")'));
  const V = await (await b.newContext()).newPage();
  await V.goto(BASE + '/e/show-com-taxa');
  t = (await V.locator('.pub-lote:has-text("Barato")').innerText()).replace(/\s+/g, ' ');
  ok(t.includes('R$ 6,00') && t.includes('R$ 5,00 + R$ 1,00 de taxa'), 'página do evento: preço com a taxa separada');

  // evento muda para embutida
  await P.goto(BASE + '/painel/eventos');
  await nav(P, P.click('a:has-text("Show com Taxa")'));
  await nav(P, P.click('a:has-text("Editar")'));
  await P.selectOption('#c_taxa_comprador', '0');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  ok(sql('SELECT taxa_comprador FROM evento') === '0', 'evento passa a ter taxa embutida (só ele)');
  ok((await precoLinha('Barato')).includes('você recebe R$ 4,00') && (await precoLinha('Pista')).includes('você recebe R$ 95,00'), 'painel: embutida → você recebe (preço − taxa)');
  await V.reload();
  t = (await V.locator('.pub-lote:has-text("Barato")').innerText()).replace(/\s+/g, ' ');
  ok(t.includes('R$ 5,00') && !t.includes('de taxa'), 'página do evento: embutida mostra só o preço');

  // isenção da empresa zera a taxa
  sql(`INSERT INTO taxa_organizador (organizador_id, operacao, isento, inicio) VALUES (${org}, 'todas', 1, CURDATE())`);
  await P.reload();
  ok(!(await precoLinha('Barato')).includes('você recebe'), 'empresa isenta: sem taxa no painel');
  sql('DELETE FROM taxa_organizador');

  // textos do site
  const S = await (await b.newContext()).newPage();
  await S.goto(BASE + '/precos');
  ok((await S.content()).includes('Taxa embutida ou repassada') && !(await S.content()).includes('sem taxa para quem compra'), 'Preços explica a escolha');
  await S.goto(BASE + '/ajuda');
  ok((await S.content()).includes('Cada organizador escolhe se absorve a taxa'), 'Ajuda responde sobre a taxa');

  sql("UPDATE taxa_regra SET percentual=0, valor_fixo=0, valor_minimo=0 WHERE operacao='ingresso_pago'");
  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
