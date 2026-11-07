// Teste de ponta a ponta: botão "Usar endereço da organização" no cadastro de eventos (Playwright).
// Rode contra um banco limpo:  BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-endereco.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);
const BOTAO = 'button:has-text("Usar endereço da organização")';

(async () => {
  const b = await chromium.launch(); const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const erros = []; P.on('pageerror', (e) => erros.push(e.message));
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno End'], ['email', 'bruno@end.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));

  // sem endereço na organização: botão desativado, com orientação
  await P.goto(BASE + '/painel/eventos/novo');
  ok(await P.isDisabled(BOTAO) && (await P.content()).includes('Cadastre o endereço em'), 'sem endereço na organização, botão desativado com orientação');

  sql("UPDATE organizador SET cep='30140071', logradouro='Rua da Bahia', numero='1148', complemento='Loja 2', bairro='Centro', cidade='Belo Horizonte', uf='MG' WHERE documento='41902878000108'");
  await P.goto(BASE + '/painel/eventos/novo');
  ok(await P.isVisible(BOTAO) && await P.isEnabled(BOTAO), 'presencial: botão ao lado do formato');
  await P.check('[name=formato][value=online]', { force: true });
  ok(!(await P.isVisible(BOTAO)), 'online: botão some');
  await P.check('[name=formato][value=hibrido]', { force: true });
  ok(await P.isVisible(BOTAO), 'híbrido: botão aparece');
  await P.check('[name=formato][value=presencial]', { force: true });

  await P.click(BOTAO);
  const v = async (n) => P.inputValue(`[name="local[${n}]"]`);
  ok(await v('nome') === 'Estação Slod' && await v('logradouro') === 'Rua da Bahia' && await v('numero') === '1148' && await v('complemento') === 'Loja 2'
    && await v('bairro') === 'Centro' && await v('cidade') === 'Belo Horizonte' && await v('uf') === 'MG' && await v('cep') === '30140-071',
  'preenche nome, endereço completo e CEP (com máscara)');

  // com local já preenchido, pergunta antes de substituir
  await P.fill('[name="local[nome]"]', 'Outro Lugar');
  P.once('dialog', (d) => d.dismiss());
  await P.click(BOTAO);
  ok(await v('nome') === 'Outro Lugar', 'cancelar a confirmação mantém o que foi digitado');
  P.once('dialog', (d) => d.accept());
  await P.click(BOTAO);
  ok(await v('nome') === 'Estação Slod', 'confirmar substitui pelo endereço da organização');

  await P.fill('[name=titulo]', 'Samba no Endereco'); await P.fill('[name=capacidade]', '80');
  await P.fill('[name=data_inicio]', local(9)); await P.fill('[name=data_fim]', local(10, '01:00'));
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  ok((await P.content()).includes('Evento criado'), 'evento salvo');
  ok(sql("SELECT CONCAT_WS('|', l.nome, l.cep, l.logradouro, l.numero, l.bairro, l.cidade, l.uf) FROM evento e JOIN local l ON l.id=e.local_id WHERE e.slug='samba-no-endereco'")
    === 'Estação Slod|30140071|Rua da Bahia|1148|Centro|Belo Horizonte|MG', 'local do evento gravado com o endereço da organização');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
