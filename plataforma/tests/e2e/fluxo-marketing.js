// Opções de marketing (contas SMTP da empresa) e Divulgação → Listas de e-mail (V017). Banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-marketing.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs'); const os = require('os'); const path = require('path');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

// SMTP falso na porta 2525: guarda as mensagens recebidas
const net = require('net'); const recebidos = [];
net.createServer((s) => {
  let dados = false; let buf = ''; let msg = '';
  s.write('220 falso\r\n');
  s.on('data', (d) => {
    buf += d.toString('latin1');
    let i;
    while ((i = buf.indexOf('\r\n')) >= 0) {
      const l = buf.slice(0, i); buf = buf.slice(i + 2);
      if (dados) { if (l === '.') { dados = false; recebidos.push(msg); msg = ''; s.write('250 ok\r\n'); } else msg += l + '\n'; continue; }
      const cmd = l.slice(0, 4).toUpperCase();
      if (cmd === 'EHLO') s.write('250-falso\r\n250 AUTH PLAIN LOGIN\r\n');
      else if (cmd === 'AUTH') s.write('235 ok\r\n');
      else if (cmd === 'DATA') { dados = true; s.write('354 go\r\n'); }
      else if (cmd === 'QUIT') { s.write('221 tchau\r\n'); s.end(); }
      else s.write('250 ok\r\n');
    }
  });
  s.on('error', () => {});
}).listen(2526);

(async () => {
  const b = await chromium.launch(); const erros = [];
  const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage(); P.on('pageerror', (e) => erros.push(e.message));
  P.on('dialog', (d) => d.accept());
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Mkt'], ['email', 'bruno@mkt.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));

  // SMTP
  await P.goto(BASE + '/painel/organizacao');
  ok(await P.isVisible('a:has-text("Configurar e-mails de divulgação")'), 'Organização tem Opções de marketing');
  await nav(P, P.click('a:has-text("Configurar e-mails de divulgação")'));
  const f = P.locator('form[action$="/marketing/smtp"]');
  for (const [n, v] of [['nome', 'Divulgação'], ['remetente_nome', 'Estação Slod'], ['remetente_email', 'divulga@slod.test'], ['host', '127.0.0.1'], ['usuario', 'divulga@slod.test'], ['senha', 'segredo-smtp']]) await f.locator(`[name=${n}]`).fill(v);
  await f.locator('[name=porta]').selectOption('2525'); await f.locator('[name=seguranca]').selectOption('nenhuma');
  await nav(P, f.locator('button').click());
  ok((await P.content()).includes('Conta “Divulgação” salva'), 'conta SMTP salva');
  const cripto = sql("SELECT senha_cripto FROM smtp_conta");
  ok(cripto.length > 40 && !cripto.includes('segredo-smtp'), 'senha gravada criptografada');
  await P.click('button:has-text("Enviar teste")');
  await nav(P, P.click('button:has-text("Enviar teste agora")'));
  ok((await P.content()).includes('O envio falhou') && sql("SELECT ultimo_erro IS NOT NULL FROM smtp_conta") === '1', 'teste com servidor fora do ar mostra o erro sem quebrar');
  await P.click('button:has-text("Editar")');
  const e = P.locator('form[action*="/marketing/smtp/"]:not([action$="testar"]):not([action$="remover"])');
  await e.locator('[name=limite_hora]').fill('250');
  await nav(P, e.locator('button:has-text("Salvar conta")').click());
  ok(sql("SELECT CONCAT(limite_hora,'|',senha_cripto=" + `'${cripto}'` + ") FROM smtp_conta") === '250|1', 'editar sem senha mantém a senha');

  // Listas
  await P.goto(BASE + '/painel');
  await nav(P, P.click('.menu-lateral a:has-text("Listas de e-mail")'));
  await P.fill('[name=nome]', 'Clientes antigos');
  await nav(P, P.click('button:has-text("Criar lista")'));
  ok((await P.content()).includes('Lista “Clientes antigos” criada'), 'lista criada');
  await P.fill('[name=texto]', 'Ana Souza; ana@x.test\nbeto@x.test\nnao-e-email\nANA@x.test');
  await P.evaluate(() => document.querySelectorAll('[name=declaracao]').forEach((i) => i.removeAttribute('required')));
  await nav(P, P.click('button:has-text("Adicionar")'));
  ok((await P.content()).includes('Marque a declaração') || sql("SELECT COUNT(*) FROM lista_email_contato") === '0', 'sem a declaração não adiciona');
  await P.fill('[name=texto]', 'Ana Souza; ana@x.test\nbeto@x.test\nnao-e-email\nANA@x.test');
  await P.locator('form:has([name=texto]) [name=declaracao]').check();
  await nav(P, P.click('button:has-text("Adicionar")'));
  ok((await P.content()).includes('2 e-mail(s) colados') && (await P.content()).includes('1 linha(s) sem e-mail válido'), 'colar: 2 novos, repetido e inválido ignorados');
  ok(sql("SELECT CONCAT(nome,'|',consentimento_em IS NOT NULL) FROM lista_email_contato WHERE email='ana@x.test'") === 'Ana Souza|1', 'colar guarda o nome e o consentimento');
  const csv = path.join(os.tmpdir(), 'lista-teste.csv');
  fs.writeFileSync(csv, Buffer.concat([Buffer.from('Nome;E-mail;Celular\r\n'), Buffer.from('Jos\xe9 da Silva;jose@x.test;(31) 99999-0000\r\n', 'latin1'), Buffer.from('Beto;beto@x.test;\r\n;;\r\nSem email;semarroba;\r\n')]));
  await P.setInputFiles('[name=arquivo]', csv);
  await P.locator('form:has([name=arquivo]) [name=declaracao]').check();
  await nav(P, P.click('button:has-text("Importar")'));
  ok((await P.content()).includes('1 e-mail(s) importados do arquivo') && (await P.content()).includes('1 já estavam na lista'), 'CSV: 1 novo, 1 repetido');
  ok(sql("SELECT CONCAT(nome,'|',telefone) FROM lista_email_contato WHERE email='jose@x.test'") === 'José da Silva|31999990000', 'CSV do Excel (Windows-1252) com acento e telefone');
  ok((await P.content()).includes('Nenhum evento ainda'), 'trazer de eventos: sem eventos, avisa');
  sql("INSERT INTO email_descadastro (organizador_id, email) SELECT organizador_id, 'beto@x.test' FROM lista_email LIMIT 1");
  await P.reload();
  ok((await P.locator('tr:has-text("beto@x.test")').innerText()).includes('Descadastrado'), 'descadastrado aparece marcado');
  const dl = await Promise.all([P.waitForEvent('download'), P.click('a:has-text("Exportar CSV")')]);
  const exp = fs.readFileSync(await dl[0].path(), 'utf8');
  ok(exp.includes('jose@x.test;"José da Silva"') || exp.includes('jose@x.test;José da Silva'), 'exportar CSV');
  // Convite por e-mail: conta apontando para o SMTP falso, evento publicado
  sql("UPDATE smtp_conta SET porta=2526, limite_hora=2, ultimo_erro=NULL");
  const listaUrl = P.url();
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Festa do Convite');
  const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
  await P.fill('[name=data_inicio]', local(10)); await P.fill('[name=data_fim]', local(11, '02:00'));
  await P.fill('[name="local[nome]"]', 'Galpão'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  const fl = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await fl.locator('[name=quantidade]').fill('100');
  await nav(P, fl.locator('button:has-text("Adicionar lote")').click());
  await nav(P, P.click('button:has-text("Publicar evento")'));
  await nav(P, P.click('.menu-lateral a:has-text("Convite por e-mail")'));
  const previa = await P.frameLocator('[data-convite-previa]').locator('body').innerText();
  ok(previa.includes('Festa do Convite') && previa.includes('Garantir meu ingresso') && previa.includes('Não quero mais receber'), 'pré-visualização montada do evento');
  await P.fill('[name=assunto]', 'Venha para a Festa!');
  await nav(P, P.click('button:has-text("Enviar um teste para mim")'));
  ok((await P.content()).includes('Teste enviado para bruno@mkt.test') && recebidos.some((m) => m.includes('[Teste] Venha')), 'teste chega pelo SMTP da empresa');
  await nav(P, P.click('button:has-text("Enviar para a lista")'));
  ok((await P.content()).includes('Convite na fila para 2 e-mail(s)'), 'fila com 2 (descadastrado fora): ' + sql("SELECT total_destinatarios FROM campanha"));
  recebidos.length = 0;
  await new Promise((r) => require('child_process').exec('php artisan convites:enviar', { cwd: __dirname + '/../..' }, (e, o, se) => { if (e) console.log(o, se); r(); }));
  ok(recebidos.length === 2 && recebidos.every((m) => /List-Unsubscribe: <http/.test(m) && m.includes('Subject: Venha para a Festa!')), 'cron envia com List-Unsubscribe: ' + recebidos.length);
  sql("INSERT INTO lista_email_contato (lista_id, organizador_id, email) SELECT id, organizador_id, 'novo@x.test' FROM lista_email");
  await P.goto(BASE + '/painel/convites');
  await P.fill('[name=assunto]', 'Segunda chamada');
  await nav(P, P.click('button:has-text("Enviar para a lista")'));
  ok((await P.content()).includes('Convite na fila para 1 e-mail(s)'), 'segundo convite do mesmo evento só para quem não recebeu');
  await new Promise((r) => require('child_process').exec('php artisan convites:enviar', { cwd: __dirname + '/../..' }, (e, o, se) => { if (e) console.log(o, se); r(); }));
  ok(recebidos.length === 2, 'limite por hora da conta respeitado (2/hora)');
  await P.reload();
  ok((await P.content()).includes('Pausar') && (await P.content()).includes('Faltam'), 'acompanhamento mostra o que falta');
  await nav(P, P.click('button:has-text("Cancelar envio")'));
  ok(sql("SELECT status FROM campanha ORDER BY id DESC LIMIT 1") === 'cancelada', 'cancelar envio');
  // descadastro pelo link do e-mail
  const m = recebidos[0].replace(/\n\s+/g, '').match(/List-Unsubscribe: <([^>]+)>/);
  const link = m[1].replace(/^https?:\/\/[^/]+/, BASE);
  const D = await (await b.newContext()).newPage();
  await D.goto(link);
  await nav(D, D.click('button:has-text("Não quero mais receber")'));
  ok((await D.content()).includes('não vai mais receber convites'), 'descadastro pelo link');
  ok(sql("SELECT COUNT(*) FROM email_descadastro") === '2', 'descadastro gravado');
  await D.goto(link.replace('signature=', 'signature=x'));
  ok((await D.content()).includes('403') || (await D.title()).includes('403') || !(await D.content()).includes('Não quero mais receber</button>'), 'link adulterado recusado');
  await P.goto(listaUrl);
  await nav(P, P.locator('tr:has-text("ana@x.test") button:has-text("Remover")').click());
  ok(sql("SELECT COUNT(*) FROM lista_email_contato") === '3', 'remover contato');
  await P.click('button:has-text("Renomear ou excluir")');
  await nav(P, P.click('button:has-text("Excluir esta lista")'));
  ok(sql("SELECT COUNT(*) FROM lista_email") === '0' && sql("SELECT COUNT(*) FROM lista_email_contato") === '0', 'excluir lista apaga os contatos');
  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close(); process.exit();
})();
