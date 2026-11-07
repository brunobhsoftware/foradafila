// Teste de ponta a ponta: política de cancelamento pré-preenchida com os dados da organização
// e botão "Usar texto padrão" (Playwright). Rode contra um banco limpo:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-politica.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);

(async () => {
  const b = await chromium.launch(); const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const erros = []; P.on('pageerror', (e) => erros.push(e.message));
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Pol'], ['email', 'bruno@pol.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estação Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));

  await P.goto(BASE + '/painel/eventos/novo');
  const texto = await P.inputValue('[name=politica_cancelamento]');
  ok(texto.startsWith('Este evento é organizado e realizado por Estação Slod Ltda, CNPJ 41.902.878/0001-08'), 'novo evento: política já vem com razão social e CNPJ');
  ok(texto.includes('art. 49 do Código de Defesa do Consumidor') && texto.includes('7 (sete) dias'), 'cita o arrependimento de 7 dias (art. 49 do CDC)');
  ok(texto.includes('e-mail sac@slod.test ou WhatsApp (31) 98888-7777'), 'traz o e-mail e o WhatsApp da organização');
  ok((await P.content()).includes('não substitui orientação jurídica'), 'avisa que é modelo e deve ser revisado');

  // salvar com o texto pré-preenchido
  await P.fill('[name=titulo]', 'Politica Teste'); await P.fill('[name=capacidade]', '50');
  await P.fill('[name=data_inicio]', local(8)); await P.fill('[name=data_fim]', local(9, '01:00'));
  await P.fill('[name="local[nome]"]', 'Estação Slod'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  ok(sql("SELECT politica_cancelamento LIKE 'Este evento é organizado e realizado por Estação Slod Ltda%' FROM evento WHERE slug='politica-teste'") === '1', 'texto gravado no evento');

  // edição: botão "Usar texto padrão" pergunta antes de substituir texto próprio
  await P.goto(P.url() + '/editar');
  await P.fill('[name=politica_cancelamento]', 'Minha política própria.');
  P.once('dialog', (d) => d.dismiss());
  await P.click('button:has-text("Usar texto padrão")');
  ok(await P.inputValue('[name=politica_cancelamento]') === 'Minha política própria.', 'cancelar mantém o texto próprio');
  P.once('dialog', (d) => d.accept());
  await P.click('button:has-text("Usar texto padrão")');
  ok((await P.inputValue('[name=politica_cancelamento]')).startsWith('Este evento é organizado'), 'confirmar restaura o texto padrão');
  await P.fill('[name=politica_cancelamento]', '');
  await P.click('button:has-text("Usar texto padrão")');
  ok((await P.inputValue('[name=politica_cancelamento]')).startsWith('Este evento é organizado'), 'campo vazio: preenche sem perguntar');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
