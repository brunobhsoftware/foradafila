// Teste de ponta a ponta de Mensagens: modelo da organização e do evento (usado no e-mail de confirmação),
// variáveis, pré-visualização, restaurar padrão, comunicado aos compradores/participantes e histórico. Banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-mensagens.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const formularioLegado = require('./_formulario-legado');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const codEv = (slug) => sql(`SELECT SUBSTRING(REPLACE(uuid,'-',''),1,10) FROM evento WHERE slug='${slug}'`);
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

(async () => {
  const b = await chromium.launch(); const erros = [];
  const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage(); P.on('pageerror', (e) => erros.push(e.message));
  P.on('dialog', (d) => d.accept());
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Msg'], ['email', 'bruno@msg.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));
  formularioLegado(sql);
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Festa das Mensagens');
  await P.fill('[name=data_inicio]', local(10)); await P.fill('[name=data_fim]', local(11, '02:00'));
  await P.fill('[name="local[nome]"]', 'Local'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  const f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('[name=quantidade]').fill('100');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  await nav(P, P.click('button:has-text("Publicar evento")'));

  // modelo da organização
  await nav(P, P.click('.menu-lateral a:has-text("Mensagens")'));
  ok((await P.content()).includes('Usando o texto padrão') && (await P.inputValue('[name=assunto]')).includes('{{pedido_codigo}}'), 'mostra o texto padrão da confirmação');
  await P.fill('[name=assunto]', 'Bem-vindo, ');
  await P.click('[name=assunto]'); await P.press('[name=assunto]', 'End');
  await P.fill('[name=corpo_texto]', 'Oi, ');
  await P.click('[name=corpo_texto]'); await P.press('[name=corpo_texto]', 'End');
  await P.click('button.chip-var:has-text("comprador_nome")');
  await P.type('[name=corpo_texto]', '! Pedido {{pedido_codigo}} na Estação.');
  ok((await P.inputValue('[name=corpo_texto]')) === 'Oi, {{comprador_nome}}! Pedido {{pedido_codigo}} na Estação.', 'botão de variável insere no texto');
  await P.fill('[name=assunto]', 'Confirmado: {{evento_titulo}}');
  await P.click('button:has-text("Pré-visualizar")');
  await P.waitForSelector('[data-previa-caixa]:not([hidden])');
  ok((await P.locator('[data-previa-caixa]').innerText()).includes('Oi, Maria Souza! Pedido FFAB12CD'), 'pré-visualização com dados de exemplo');
  await nav(P, P.click('button:has-text("Salvar texto")'));
  ok((await P.content()).includes('Modelo salvo') && (await P.content()).includes('Texto personalizado'), 'modelo da organização salvo');

  // modelo só do evento
  await nav(P, P.selectOption('select[name=evento]', { label: 'Só o evento: Festa das Mensagens' }));
  ok((await P.content()).includes('Usando o texto da organização') && (await P.inputValue('[name=corpo_texto]')).startsWith('Oi, {{comprador_nome}}'), 'evento herda o texto da organização');
  await P.fill('[name=corpo_texto]', 'Evento especial para {{comprador_nome}}.');
  await nav(P, P.click('button:has-text("Salvar texto")'));
  ok(sql("SELECT COUNT(*) FROM modelo_mensagem WHERE organizador_id IS NOT NULL") === '2', 'dois modelos personalizados (organização e evento)');

  // inscrição usa o modelo do evento
  const V = await (await b.newContext()).newPage();
  await V.goto(BASE + '/e/' + codEv('festa-das-mensagens'));
  await V.selectOption('select[name^="q["]', '2');
  await nav(V, V.click('button:has-text("Garantir ingresso")'));
  for (const [n, v] of [['nome', 'Ana Compradora'], ['email', 'ana@publico.test'], ['senha', 'Ingresso1'], ['senha_confirmation', 'Ingresso1']]) await V.fill(`[name=${n}]`, v);
  await V.fill('[name="participantes[0][nome]"]', 'Ana Compradora'); await V.fill('[name="participantes[0][cpf]"]', '529.982.247-25');
  await V.fill('[name="participantes[1][nome]"]', 'Pedro Amigo'); await V.fill('[name="participantes[1][cpf]"]', '153.509.460-56');
  for (const t of ['compra_comprador', 'privacidade']) await V.check(`[name="aceites[${t}]"]`);
  await nav(V, V.click('button:has-text("Confirmar inscrição")'));
  ok(sql("SELECT assunto FROM mensagem_envio WHERE tipo='confirmacao_compra' ORDER BY id DESC LIMIT 1") === 'Confirmado: Festa das Mensagens'
    && sql("SELECT corpo_renderizado LIKE '%Evento especial para Ana Compradora%' FROM mensagem_envio WHERE tipo='confirmacao_compra' ORDER BY id DESC LIMIT 1") === '1', 'e-mail de confirmação usa o texto do evento');

  // restaurar: remove o do evento, volta a valer o da organização
  await P.goto(BASE + '/painel/mensagens');
  await nav(P, P.selectOption('select[name=evento]', { label: 'Só o evento: Festa das Mensagens' }));
  await nav(P, P.click('button:has-text("Remover o texto próprio deste evento")'));
  ok((await P.content()).includes('vale o texto da organização') && sql("SELECT COUNT(*) FROM modelo_mensagem WHERE organizador_id IS NOT NULL AND ativo=1") === '1', 'remover o texto do evento');

  // comunicado
  sql("UPDATE participante SET email='pedro@publico.test' WHERE nome='Pedro Amigo'");
  await P.goto(BASE + '/painel/mensagens');
  await P.selectOption('#comunicado select[name=destino]', 'participantes');
  await P.fill('#comunicado [name=assunto]', 'Mudança de horário'); await P.fill('#comunicado [name=texto]', 'Os portões abrem às 19h.');
  await nav(P, P.click('button:has-text("Enviar comunicado")'));
  ok((await P.content()).includes('Comunicado enviado para 2 e-mail(s)'), 'comunicado para os participantes (2 e-mails)');
  ok(sql("SELECT COUNT(*) FROM mensagem_envio WHERE tipo='comunicado' AND assunto='Mudança de horário'") === '2', 'comunicados registrados em mensagem_envio');
  await P.selectOption('#comunicado select[name=destino]', 'compradores');
  await P.fill('#comunicado [name=assunto]', 'Aviso'); await P.fill('#comunicado [name=texto]', 'Teste.');
  await nav(P, P.click('button:has-text("Enviar comunicado")'));
  ok((await P.content()).includes('Comunicado enviado para 1 e-mail(s)'), 'comunicado para os compradores (1 por pedido)');

  // histórico
  const hist = await P.locator('section:has-text("Últimos envios") table').innerText();
  ok(hist.includes('comunicado') && hist.includes('confirmacao_compra') && hist.includes('pedro@publico.test'), 'histórico de envios');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
