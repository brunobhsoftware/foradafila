// Opções de marketing (contas SMTP da empresa) e Divulgação → Listas de e-mail (V017). Banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-marketing.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs'); const os = require('os'); const path = require('path');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

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
  await nav(P, P.click('button:has-text("Adicionar")'));
  ok((await P.content()).includes('2 e-mail(s) colados') && (await P.content()).includes('1 linha(s) sem e-mail válido'), 'colar: 2 novos, repetido e inválido ignorados');
  ok(sql("SELECT nome FROM lista_email_contato WHERE email='ana@x.test'") === 'Ana Souza', 'colar guarda o nome');
  const csv = path.join(os.tmpdir(), 'lista-teste.csv');
  fs.writeFileSync(csv, Buffer.concat([Buffer.from('Nome;E-mail;Celular\r\n'), Buffer.from('Jos\xe9 da Silva;jose@x.test;(31) 99999-0000\r\n', 'latin1'), Buffer.from('Beto;beto@x.test;\r\n;;\r\nSem email;semarroba;\r\n')]));
  await P.setInputFiles('[name=arquivo]', csv);
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
  await nav(P, P.locator('tr:has-text("ana@x.test") button:has-text("Remover")').click());
  ok(sql("SELECT COUNT(*) FROM lista_email_contato") === '2', 'remover contato');
  await P.click('button:has-text("Renomear ou excluir")');
  await nav(P, P.click('button:has-text("Excluir esta lista")'));
  ok(sql("SELECT COUNT(*) FROM lista_email") === '0' && sql("SELECT COUNT(*) FROM lista_email_contato") === '0', 'excluir lista apaga os contatos');
  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
