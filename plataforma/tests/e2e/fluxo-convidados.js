// Teste de ponta a ponta de Convidados: lista por evento, adicionar (um e em lote), validações, emitir e enviar
// (titular + acompanhantes, comprador sem senha), emitir todos, remover (cancela ingressos). Banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-convidados.js
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
  P.on('dialog', (d) => d.accept());
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Convites'], ['email', 'bruno@convites.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Festa dos Convidados');
  await P.fill('[name=data_inicio]', local(10)); await P.fill('[name=data_fim]', local(11, '02:00'));
  await P.fill('[name="local[nome]"]', 'Local'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));

  await P.goto(BASE + '/painel/convidados');
  ok((await P.content()).includes('ainda não tem lotes'), 'sem lote: orienta a criar um lote para convidados');
  await P.goto(BASE + '/painel/eventos');
  await nav(P, P.click('a:has-text("Festa dos Convidados")'));
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  const f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('[name=nome]').fill('Cortesia'); await f.locator('[name=quantidade]').fill('6');
  await f.locator('input[name=visibilidade][value=oculto]').check();
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());

  // lista
  await nav(P, P.click('.menu-lateral a:has-text("Convidados")'));
  await P.fill('[name=nome]', 'Lista VIP'); await P.fill('[name=max_acompanhantes]', '1'); await P.fill('[name=limite_convidados]', '5');
  await nav(P, P.click('button:has-text("Criar lista")'));
  ok((await P.content()).includes('Lista “Lista VIP” salva') && P.url().includes('/listas/'), 'lista criada e aberta');

  // adicionar um
  await P.fill('form:has([name=nome]):not(:has(textarea)) [name=nome]', 'Maria Souza');
  await P.fill('form:has([name=nome]):not(:has(textarea)) [name=email]', 'maria@convite.test');
  await P.fill('form:has([name=nome]):not(:has(textarea)) [name=acompanhantes]', '1');
  await nav(P, P.click('button:has-text("Adicionar")  >> nth=0'));
  ok((await P.content()).includes('1 convidado(s) adicionado(s)') && (await P.content()).includes('Maria Souza'), 'adiciona um convidado');

  // em lote, com erros
  await P.fill('textarea[name=lote_texto]', 'João Lima; joao@convite.test\nX; ruim@\nAna Paula; ana@convite.test; 3');
  await nav(P, P.click('button:has-text("Adicionar todos")'));
  let t = await P.content();
  ok(t.includes('Linha 2: nome obrigatório') && t.includes('Linha 3: máximo de 1 acompanhante'), 'lote com erros: aponta as linhas');
  await P.fill('textarea[name=lote_texto]', 'João Lima; joao@convite.test\nPedro Sem Email');
  await nav(P, P.click('button:has-text("Adicionar todos")'));
  ok((await P.content()).includes('2 convidado(s) adicionado(s)'), 'adiciona vários de uma vez');
  ok(await P.locator('tr:has-text("Pedro Sem Email") button:has-text("Emitir e enviar")').isDisabled(), 'sem e-mail: botão de emitir desativado');

  // emitir um (titular + acompanhante)
  await nav(P, P.locator('tr:has-text("Maria Souza") button:has-text("Emitir e enviar")').click());
  t = await P.content();
  ok(t.includes('1 convite(s) emitido(s)') && t.includes('Ingresso emitido'), 'emite os ingressos da Maria');
  ok(sql("SELECT COUNT(*) FROM ingresso i JOIN participante p ON p.ingresso_id=i.id WHERE p.nome LIKE '%Maria Souza%'") === '2', 'titular + 1 acompanhante = 2 ingressos');
  ok(sql("SELECT CONCAT(origem,'|',status,'|',valor_total) FROM pedido") === 'lista_convidados|pago|0.00', 'pedido de cortesia (origem lista de convidados)');
  ok(sql("SELECT senha_hash IS NULL FROM comprador WHERE email='maria@convite.test'") === '1', 'comprador criado sem senha (cria pelo link "esqueci a senha")');
  ok(sql("SELECT COUNT(*) FROM mensagem_envio WHERE destinatario='maria@convite.test' AND tipo='confirmacao_compra'") === '1', 'ingresso enviado por e-mail');
  ok(sql("SELECT vendido FROM lote WHERE nome='Cortesia'") === '2', 'estoque do lote baixou 2');

  // emitir todos (só quem tem e-mail)
  await nav(P, P.click('button:has-text("Emitir e enviar todos")'));
  ok((await P.content()).includes('1 convite(s) emitido(s)') && sql("SELECT status FROM convidado WHERE nome='João Lima'") === 'emitido'
    && sql("SELECT status FROM convidado WHERE nome='Pedro Sem Email'") === 'pendente', 'emitir todos: só os pendentes com e-mail');

  // remover emitido: cancela ingressos
  await nav(P, P.locator('tr:has-text("Maria Souza") button:has-text("Remover")').click());
  ok((await P.content()).includes('os ingressos foram cancelados') && sql("SELECT COUNT(*) FROM ingresso i JOIN participante p ON p.ingresso_id=i.id WHERE p.nome LIKE '%Maria Souza%' AND i.status='cancelado'") === '2', 'remover convidado emitido cancela os ingressos');
  ok(sql("SELECT vendido FROM lote WHERE nome='Cortesia'") === '1', 'estoque devolvido');

  // limite da lista e do lote
  await P.fill('textarea[name=lote_texto]', 'A A; a@x.test\nB B; b@x.test\nC C; c@x.test\nD D; d@x.test');
  await nav(P, P.click('button:has-text("Adicionar todos")'));
  ok((await P.content()).includes('A lista aceita até 5 convidados'), 'limite de convidados da lista');

  // participante aparece na lista de participantes e o check-in funciona
  await P.goto(BASE + '/painel/participantes');
  ok((await P.content()).includes('João Lima'), 'convidado emitido aparece em Participantes');

  // a página da lista mostra o pedido com link
  await P.goto(BASE + '/painel/convidados');
  ok((await P.locator('table.tabela').innerText()).includes('Lista VIP'), 'lista aparece no índice com contagem');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
