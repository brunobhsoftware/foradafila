// Teste de ponta a ponta de Vendas: Participantes (lista, busca, reenviar ingresso, cancelar com valor a
// devolver, exportar CSV) e Pedidos (lista, detalhe, reenviar confirmação, cancelar pedido). Banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-vendas.js
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
  for (const [n, v] of [['nome', 'Bruno Vendas'], ['email', 'bruno@vendas.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));
  await P.goto(BASE + '/painel/participantes');
  ok((await P.content()).includes('Nenhum evento ainda'), 'sem evento: orienta a criar');

  // evento + lote gratuito + publicar
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Festa das Vendas');
  await P.fill('[name=data_inicio]', local(15)); await P.fill('[name=data_fim]', local(16, '02:00'));
  await P.fill('[name="local[nome]"]', 'Estação Slod'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  const f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('[name=quantidade]').fill('100');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  await nav(P, P.click('button:has-text("Publicar evento")'));

  // comprador se inscreve com 3 ingressos
  const V = await (await b.newContext()).newPage(); V.on('pageerror', (e) => erros.push(e.message));
  await V.goto(BASE + '/e/festa-das-vendas');
  await V.selectOption('select[name^="q["]', '3');
  await nav(V, V.click('button:has-text("Garantir ingresso")'));
  for (const [n, v] of [['nome', 'Ana Compradora'], ['email', 'ana@publico.test'], ['senha', 'Ingresso1'], ['senha_confirmation', 'Ingresso1']]) await V.fill(`[name=${n}]`, v);
  for (const [i, nome, cpf] of [[0, 'Ana Compradora', '529.982.247-25'], [1, 'Pedro Amigo', '153.509.460-56'], [2, 'Carla Prima', '111.444.777-35']]) {
    await V.fill(`[name="participantes[${i}][nome]"]`, nome); await V.fill(`[name="participantes[${i}][cpf]"]`, cpf);
  }
  for (const t of ['compra_comprador', 'privacidade']) await V.check(`[name="aceites[${t}]"]`);
  await nav(V, V.click('button:has-text("Confirmar inscrição")'));
  ok(V.url().endsWith('/minha-conta'), 'comprador inscrito com 3 ingressos');
  // simula ingresso pago (venda paga ainda não existe): Pedro pagou R$ 80
  sql("UPDATE ingresso i JOIN participante p ON p.ingresso_id=i.id SET i.valor_pago=80 WHERE p.nome='Pedro Amigo'");
  sql("UPDATE participante SET email='carla@publico.test' WHERE nome='Carla Prima'");

  // ---- Participantes ----
  await nav(P, P.click('.menu-lateral a:has-text("Participantes")'));
  let t = await P.content();
  ok(t.includes('Festa das Vendas') && t.includes('Ana Compradora') && t.includes('Pedro Amigo') && t.includes('Carla Prima') && (t.match(/FF-\d{4}-\d{6}/g) || []).length >= 3, 'lista de participantes com número do ingresso');
  ok((await P.locator('.kpi:has-text("Válidos") b').innerText()) === '3', 'resumo: 3 válidos');
  await P.fill('[name=busca]', 'pedro'); await nav(P, P.click('button:has-text("Filtrar")'));
  t = await P.locator('table.tabela tbody').innerText();
  ok(t.includes('Pedro Amigo') && !t.includes('Carla Prima'), 'busca por nome');
  const numCarla = sql("SELECT i.numero FROM ingresso i JOIN participante p ON p.ingresso_id=i.id WHERE p.nome='Carla Prima'");
  await P.fill('[name=busca]', numCarla); await nav(P, P.click('button:has-text("Filtrar")'));
  ok((await P.locator('table.tabela tbody').innerText()).includes('Carla Prima'), 'busca pelo número do ingresso');
  await P.fill('[name=busca]', ''); await nav(P, P.click('button:has-text("Filtrar")'));

  // reenviar para participante (Carla tem e-mail) e para comprador
  const linhaCarla = P.locator('tr:has-text("Carla Prima")').first();
  const acoesCarla = P.locator('tr.linha-acoes:has-text("carla@publico.test")');
  await linhaCarla.locator('button:has-text("Ações")').click();
  await nav(P, acoesCarla.locator('button:has-text("Para o participante")').click());
  ok((await P.content()).includes('reenviado para carla@publico.test'), 'reenvia o ingresso para o e-mail do participante');
  ok(sql("SELECT COUNT(*) FROM mensagem_envio WHERE destinatario='carla@publico.test' AND tipo='confirmacao_compra'") === '1', 'e-mail registrado em mensagem_envio');
  const linhaAna = P.locator('tr:not(.linha-acoes):has-text("Ana Compradora")').first();
  await linhaAna.locator('button:has-text("Ações")').click();
  await nav(P, P.locator('tr.linha-acoes:visible button:has-text("Para o comprador")').click());
  ok((await P.content()).includes('reenviado para ana@publico.test'), 'reenvia para o comprador');

  // cancelar ingresso pago: mostra valor a devolver
  const botaoPedro = P.locator('tr:not(.linha-acoes):has-text("Pedro Amigo")');
  await botaoPedro.locator('button:has-text("Ações")').click();
  const linhaPedro = P.locator('tr.linha-acoes:visible');
  ok(await linhaPedro.locator('button:has-text("Para o participante")').isDisabled(), 'sem e-mail do participante, botão desativado');
  ok((await linhaPedro.innerText()).includes('Valor pago a devolver ao comprador: R$ 80,00'), 'cancelamento mostra o valor a devolver');
  await linhaPedro.locator('select[name=motivo]').selectOption('arrependimento');
  await linhaPedro.locator('input[name=observacao]').fill('Pediu pelo WhatsApp');
  P.once('dialog', (d) => d.accept());
  await nav(P, linhaPedro.locator('button:has-text("Cancelar ingresso")').click());
  ok((await P.content()).includes('Valor a devolver ao comprador: R$ 80,00'), 'ingresso cancelado com aviso do valor');
  ok(sql("SELECT i.status FROM ingresso i JOIN participante p ON p.ingresso_id=i.id WHERE p.nome='Pedro Amigo'") === 'reembolsado', 'ingresso pago fica "reembolsado"');
  ok(sql("SELECT CONCAT(valor,'|',motivo,'|',status) FROM reembolso") === '80.00|arrependimento|aprovado', 'devolução registrada (R$ 80, a devolver)');
  ok(sql("SELECT status FROM pedido") === 'reembolso_parcial' && sql('SELECT vendido FROM lote') === '2', 'pedido em reembolso parcial e estoque devolvido');
  ok(sql("SELECT COUNT(*) FROM mensagem_envio WHERE tipo='cancelamento_organizacao' AND destinatario='ana@publico.test'") === '1', 'comprador avisado por e-mail');

  // exportar CSV
  const [dl] = await Promise.all([P.waitForEvent('download'), P.click('a:has-text("Exportar (CSV)")')]);
  const csv = require('fs').readFileSync(await dl.path(), 'utf8');
  ok(csv.includes('Ingresso;Participante;E-mail;CPF') && csv.includes('Carla Prima') && csv.includes('80,00') && csv.includes('Reembolsado'), 'CSV com participantes, valor e situação');

  // ---- Pedidos ----
  await nav(P, P.click('.menu-lateral a:has-text("Pedidos")'));
  t = await P.content();
  const codigo = sql('SELECT codigo FROM pedido');
  ok(t.includes(codigo) && t.includes('Ana Compradora') && t.includes('Reembolso parcial'), 'lista de pedidos');
  await nav(P, P.click(`a:has-text("${codigo}")`));
  t = await P.content();
  ok(t.includes('Pedido ' + codigo) && t.includes('Carla Prima') && t.includes('R$ 80,00') && t.includes('A devolver'), 'detalhe do pedido com ingressos e devolução');
  await nav(P, P.click('button:has-text("Reenviar confirmação")'));
  ok((await P.content()).includes('reenviada para ana@publico.test'), 'reenviar confirmação do pedido');
  ok((await P.locator('section:has-text("Cancelar o pedido")').innerText()).includes('não há valor a devolver'), 'restante é gratuito: sem valor a devolver');
  P.once('dialog', (d) => d.accept());
  await nav(P, P.click('button:has-text("Cancelar pedido")'));
  ok((await P.content()).includes('2 ingresso(s) do pedido') && sql('SELECT status FROM pedido') === 'reembolsado' && sql('SELECT vendido FROM lote') === '0', 'cancelar pedido: todos cancelados e estoque devolvido');


  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
