// Teste de ponta a ponta: perfil "Produtor", dados bancários opcionais, reenviar convite,
// trocar senha e trocar e-mail com código enviado por e-mail (Playwright). Requer o V004.
// Os e-mails vão para storage/logs/laravel.log (sem SMTP no .env local): o teste lê o código de lá.
// Rode contra um banco limpo:  BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-conta.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const LOG = __dirname + '/../../storage/logs/laravel.log';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);
const ultimoCodigo = () => { const m = [...fs.readFileSync(LOG, 'utf8').matchAll(/letter-spacing:8px;color:#101828;margin:18px 0">(\d{6})</g)]; return m.length ? m[m.length - 1][1] : null; };
const entrar = async (p, email, senha) => {
  await p.goto(BASE + '/entrar'); await p.fill('[name=email]', email); await p.fill('[name=senha]', senha);
  await nav(p, p.click('button[type=submit]'));
};

(async () => {
  fs.writeFileSync(LOG, '');
  const b = await chromium.launch(); const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const erros = []; P.on('pageerror', (e) => erros.push(e.message));
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Conta'], ['email', 'bruno@conta.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));

  // "Dono" aparece como "Produtor"
  ok((await P.locator('.conta-menu__quem').textContent()).includes('Produtor'), 'menu da conta mostra o perfil "Produtor"');

  // menu lateral enxuto: empresa, e-mail e atalhos ficam no alto do painel e no Dashboard
  ok(await P.locator('.menu-lateral').getByText('Sair').count() === 0 && await P.locator('.menu-lateral').getByText('Trocar senha').count() === 0, 'menu lateral sem os atalhos da conta');
  const topo = await P.locator('.conta-menu summary').innerText();
  ok(topo.includes('Estação Slod') && topo.includes('sac@slod.test'), 'alto do painel mostra nome e e-mail da empresa');
  ok(!(await P.isVisible('.conta-menu__painel a:has-text("Trocar senha")')), 'menu da conta começa fechado');
  await P.click('.conta-menu summary');
  for (const t of ['Minhas preferências', 'Trocar senha', 'Minha página de eventos', 'Ir para o site', 'Sair']) ok(await P.isVisible(`.conta-menu__painel :text("${t}")`), 'menu da conta: ' + t);
  await P.click('h1');
  ok(!(await P.isVisible('.conta-menu__painel a:has-text("Trocar senha")')), 'clicar fora fecha o menu da conta');
  const atalhos = P.locator('.atalhos-org');
  ok(await atalhos.locator('a:has-text("Minha página de eventos")').getAttribute('href') === BASE + '/p/estacao-slod', 'Dashboard: "Minha página de eventos" abre /p/estacao-slod');
  for (const t of ['Minhas preferências', 'Trocar senha', 'Ir para o site', 'Sair']) ok(await atalhos.locator(`:text("${t}")`).count() === 1, 'Dashboard: atalho ' + t);
  await P.goto(BASE + '/painel/perfis');
  const perfis = await P.content();
  ok(perfis.includes('<th>Produtor</th>') && !/>Dono</.test(perfis), 'níveis de acesso: "Produtor" no lugar de "Dono"');

  // dados bancários opcionais (tudo ou nada)
  await P.goto(BASE + '/painel/organizacao');
  const salvar = async () => { await P.evaluate(() => { document.querySelector('form[action$="/painel/organizacao"]').noValidate = true; }); await nav(P, P.click('form[action$="/painel/organizacao"] .acoes-form button')); };
  await P.fill('[name=representante_nome]', 'Bruno Conta'); await P.fill('[name=representante_cpf]', '529.982.247-25');
  await salvar();
  ok((await P.content()).includes('Dados da organização salvos'), 'salva sem dados bancários');
  // dados bancários agora ficam no Financeiro (Dados para o repasse)
  await P.goto(BASE + '/painel/financeiro');
  await P.check('input[name=recebimento_preferido][value=conta]', { force: true });
  await P.fill('[name=banco]', '341 - Itaú Unibanco');
  await nav(P, P.click('button:has-text("Salvar dados de recebimento")'));
  ok((await P.content()).includes('informe banco, agência, conta e favorecido') || (await P.content()).includes('Informe a agência'), 'banco preenchido pede agência, conta e favorecido');
  await P.fill('[name=agencia]', '1234'); await P.fill('[name=conta]', '12345-6'); await P.fill('[name=favorecido]', 'Estacao Slod Ltda');
  await nav(P, P.click('button:has-text("Salvar dados de recebimento")'));
  ok((await P.content()).includes('Dados de recebimento salvos') && sql("SELECT conta FROM organizador WHERE documento='41902878000108'") === '12345-6', 'salva com os quatro dados bancários');

  // reenviar convite
  await P.goto(BASE + '/painel/equipe');
  const conv = P.locator('aside form');
  await conv.locator('[name=nome]').fill('Davi Portaria'); await conv.locator('[name=email]').fill('davi@conta.test');
  await nav(P, conv.locator('button:has-text("Gerar convite")').click());
  const link1 = await P.inputValue('#link-convite');
  await nav(P, P.click('tr:has-text("Davi Portaria") button:has-text("Reenviar convite")'));
  const link2 = await P.inputValue('#link-convite');
  ok((await P.content()).includes('Novo convite enviado por e-mail') && link1 !== link2, 'botão "Reenviar convite" gera e envia um novo link');
  await P.waitForTimeout(300);
  ok(sql("SELECT COUNT(*) FROM mensagem_envio WHERE tipo='convite_equipe' AND destinatario='davi@conta.test' AND status='enviado'") === '2', 'dois e-mails de convite enviados');
  const V = await (await b.newContext()).newPage();
  ok((await V.goto(link1)).status() === 404 && (await V.goto(link2)).status() === 200, 'link antigo deixa de valer; o novo funciona');

  // trocar senha
  await P.goto(BASE + '/painel/organizacao');
  await nav(P, P.click('#conteudo a:has-text("Trocar senha")'));
  ok(P.url().endsWith('/painel/minha-conta/senha'), 'botão "Trocar senha" abre a página');
  await nav(P, P.click('button:has-text("Enviar código por e-mail")'));
  await P.waitForTimeout(300);
  const cod1 = ultimoCodigo();
  ok(/^\d{6}$/.test(cod1 || '') && (await P.content()).includes('Enviamos um código de 6 números'), 'código de 6 dígitos enviado por e-mail');
  ok(sql("SELECT COUNT(*) FROM mensagem_envio WHERE tipo='codigo_verificacao' AND (corpo_renderizado LIKE '%" + cod1 + "%' OR assunto LIKE '%" + cod1 + "%')") === '0', 'código não fica gravado no registro de e-mails');
  await nav(P, P.click('button:has-text("Enviar outro código")'));
  ok((await P.content()).includes('Aguarde um minuto'), 'reenvio imediato é bloqueado por 60 s');
  const errado = cod1 === '000000' ? '111111' : '000000';
  await P.fill('[name=codigo]', errado); await P.fill('[name=senha]', 'NovaSenha7'); await P.fill('[name=senha_confirmation]', 'NovaSenha7');
  await nav(P, P.click('button:has-text("Trocar senha")'));
  ok((await P.content()).includes('Código incorreto. Restam 4'), 'código errado é recusado');
  await P.fill('[name=codigo]', cod1); await P.fill('[name=senha]', 'NovaSenha7'); await P.fill('[name=senha_confirmation]', 'Diferente7');
  await nav(P, P.click('button:has-text("Trocar senha")'));
  ok((await P.content()).includes('A confirmação de nova senha não confere'), 'confirmação diferente é recusada');
  await P.fill('[name=codigo]', cod1); await P.fill('[name=senha]', 'NovaSenha7'); await P.fill('[name=senha_confirmation]', 'NovaSenha7');
  await nav(P, P.click('button:has-text("Trocar senha")'));
  ok((await P.content()).includes('Senha alterada'), 'senha trocada com o código certo');
  await P.goto(BASE + '/painel/minha-conta/senha');
  await P.fill('[name=codigo]', cod1); await P.fill('[name=senha]', 'Outra1234'); await P.fill('[name=senha_confirmation]', 'Outra1234');
  await nav(P, P.click('button:has-text("Trocar senha")'));
  ok((await P.content()).includes('expirou ou não foi pedido'), 'código não pode ser usado duas vezes');
  const L = await (await b.newContext()).newPage();
  await entrar(L, 'bruno@conta.test', 'NovaSenha7');
  ok(L.url().endsWith('/painel'), 'entra com a nova senha');

  // trocar e-mail: só depois de validar o código no e-mail atual
  sql("UPDATE usuario SET email='ocupado@conta.test' WHERE email='davi@conta.test'");
  await P.goto(BASE + '/painel/minha-conta/email');
  await P.fill('[name=codigo]', '123456'); await P.fill('[name=email]', 'novo@conta.test');
  await nav(P, P.click('button:has-text("Trocar e-mail")'));
  ok((await P.content()).includes('expirou ou não foi pedido') && sql("SELECT COUNT(*) FROM usuario WHERE email='novo@conta.test'") === '0', 'sem validar o e-mail atual, não troca');
  await nav(P, P.click('button:has-text("Enviar código por e-mail")'));
  await P.waitForTimeout(300);
  const cod2 = ultimoCodigo();
  await P.fill('[name=codigo]', cod2); await P.fill('[name=email]', 'ocupado@conta.test');
  await nav(P, P.click('button:has-text("Trocar e-mail")'));
  ok((await P.content()).includes('já é usado por outra conta'), 'e-mail de outra conta é recusado');
  await P.fill('[name=codigo]', cod2); await P.fill('[name=email]', 'Novo@Conta.test');
  await nav(P, P.click('button:has-text("Trocar e-mail")'));
  ok((await P.content()).includes('E-mail de acesso alterado para novo@conta.test'), 'e-mail trocado após validar o código');
  await P.waitForTimeout(300);
  ok(sql("SELECT COUNT(*) FROM mensagem_envio WHERE tipo='conta_alterada' AND destinatario IN ('bruno@conta.test','novo@conta.test')") === '3', 'avisos ao e-mail antigo e ao novo (e da troca de senha)');
  const L2 = await (await b.newContext()).newPage();
  await entrar(L2, 'novo@conta.test', 'NovaSenha7');
  ok(L2.url().endsWith('/painel'), 'entra com o novo e-mail');
  const L3 = await (await b.newContext()).newPage();
  await entrar(L3, 'bruno@conta.test', 'NovaSenha7');
  ok((await L3.content()).includes('E-mail ou senha incorretos'), 'e-mail antigo não entra mais');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
