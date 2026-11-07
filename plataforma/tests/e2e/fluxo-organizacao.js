// Teste de ponta a ponta: dados obrigatórios da organização, logomarca, chave da API bloqueada,
// preferências de notificação e aviso de compra para a equipe (Playwright). Requer o V004.
// Rode contra um banco limpo:  BASE=http://127.0.0.1:8000 IMG=/pasta/com/logo.png node tests/e2e/fluxo-organizacao.js
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const crypto = require('crypto');
const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const IMG = process.env.IMG;
const MIDIA = process.env.MIDIA || __dirname + '/../../storage/app/midia/logomarcas';
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);
const salvar = async (p) => { await p.evaluate(() => { document.querySelector('form[action$="/painel/organizacao"]').noValidate = true; }); return nav(p, p.click('form[action$="/painel/organizacao"] .acoes-form button')); };

(async () => {
  const b = await chromium.launch(); const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const erros = []; P.on('pageerror', (e) => erros.push(e.message));
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Slod'], ['email', 'bruno@slod.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));
  ok((await P.content()).includes('Completar os dados da organização'), 'painel pede os dados obrigatórios');

  // obrigatórios
  await P.goto(BASE + '/painel/organizacao');
  for (const t of ['Representante legal', 'CPF do representante legal', 'Banco', 'Agência', 'Conta', 'Favorecido', 'Logo', 'Chave de acesso da API'])
    ok((await P.content()).includes(t), `campo "${t}" na tela`);
  ok((await P.content()).includes('Dimensão recomendada 136x136. Formato JPEG, GIF ou PNG. Tamanho máximo de 2MB.'), 'texto da logo como pedido');
  await P.fill('[name=favorecido]', '');
  await salvar(P);
  const c1 = await P.content();
  ok(c1.includes('O campo representante legal é obrigatório') && c1.includes('Informe o CPF do representante legal')
    && !c1.includes('O campo banco é obrigatório'), 'salvar sem representante é recusado; dados bancários são opcionais');
  await P.fill('[name=representante_nome]', 'Bruno da Silva'); await P.fill('[name=representante_cpf]', '111.111.111-11');
  await P.fill('[name=banco]', '341 - Itaú Unibanco'); await P.fill('[name=agencia]', '1234'); await P.fill('[name=conta]', '12345-6'); await P.fill('[name=favorecido]', 'Estacao Slod Ltda');
  await salvar(P);
  ok((await P.content()).includes('CPF inválido'), 'CPF do representante inválido é recusado');
  await P.fill('[name=representante_cpf]', '529.982.247-25');
  await P.setInputFiles('input[name=logo]', IMG + '/logo.png');
  await P.waitForFunction(() => document.querySelector('[data-upload-status]').textContent.includes('136 x 136'));
  await salvar(P);
  ok((await P.content()).includes('Dados da organização salvos'), 'dados salvos');
  const linha = sql("SELECT CONCAT_WS('|', representante_nome, representante_cpf, banco, agencia, conta, favorecido, logo_url, uuid) FROM organizador WHERE documento='41902878000108'").split('|');
  ok(linha[1] === '52998224725' && linha[3] === '1234' && linha[4] === '12345-6', 'banco gravado (CPF só dígitos, agência e conta)');
  const nomeLogo = linha[6].split('/').pop().split('?')[0];
  ok(nomeLogo === `estacao-slod_${linha[7]}.png` && fs.readdirSync(MIDIA).includes(nomeLogo), 'logo gravada como logomarcas/' + nomeLogo);
  ok((await P.request.get(BASE + linha[6])).headers()['content-type'] === 'image/png', 'logo servida em /midia/logomarcas');
  ok(await P.locator('.menu-lateral__logo').count() === 1, 'logo aparece no menu do painel');
  ok(!(await P.content()).includes('Completar os campos obrigatórios'), 'aviso de obrigatórios some depois de completar');

  // chave da API: bloqueada por enquanto (o token de integração vem depois)
  ok(await P.isDisabled('#c_chave_api') && await P.locator('button:has-text("Gerar")').count() === 0, 'chave da API bloqueada, sem botão de gerar');
  ok((await P.request.post(BASE + '/painel/organizacao/chave-api')).status() !== 200 && sql("SELECT IFNULL(api_chave_hash,'vazio') FROM organizador WHERE documento='41902878000108'") === 'vazio', 'não há como gerar chave pelo servidor');

  // preferências
  await P.goto(BASE + '/painel/preferencias');
  ok((await P.content()).includes('Notificar-me cada vez que um participante realizar uma compra') && (await P.content()).includes('Quero receber as novidades do Fora da Fila por e-mail'), 'textos das preferências');
  await P.check('[name=notificar_compra]'); await P.check('[name=receber_novidades]');
  await nav(P, P.click('.acoes-form button'));
  ok(sql("SELECT CONCAT(notificar_compra, IF(receber_novidades_em IS NULL, 'n', 's')) FROM organizador_usuario ou JOIN usuario u ON u.id=ou.usuario_id WHERE u.email='bruno@slod.test'") === '1s', 'preferências gravadas com data do consentimento');

  // evento gratuito + inscrição de um comprador → aviso para o produtor
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Roda na Estação'); await P.fill('[name=capacidade]', '50');
  await P.fill('[name=data_inicio]', local(10)); await P.fill('[name=data_fim]', local(11, '01:00'));
  await P.fill('[name="local[nome]"]', 'Estação Slod'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]'); // declaração de cancelamento (obrigatória)
  await nav(P, P.click('.acoes-form button[type=submit]'));
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  const f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('[name=preco]').fill('0'); await f.locator('[name=quantidade]').fill('50');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  await nav(P, P.click('button:has-text("Publicar evento")'));
  const V = await (await b.newContext()).newPage();
  await V.goto(BASE + '/e/roda-na-estacao');
  ok(await V.locator('img[alt^="Logo de"]').count() === 1, 'logo do organizador na página do evento');
  await V.selectOption('select[name^="q["]', '1');
  await nav(V, V.click('button:has-text("Garantir ingresso")'));
  for (const [n, v] of [['nome', 'Clara'], ['email', 'clara@pub.test'], ['senha', 'Ingresso1'], ['senha_confirmation', 'Ingresso1']]) await V.fill(`[name=${n}]`, v);
  await V.fill('[name="participantes[0][nome]"]', 'Clara'); await V.fill('[name="participantes[0][cpf]"]', '153.509.460-56');
  for (const t of ['compra_comprador', 'privacidade']) await V.check(`[name="aceites[${t}]"]`);
  await nav(V, V.click('button:has-text("Confirmar inscrição")'));
  await V.waitForTimeout(500);
  ok(sql("SELECT COUNT(*) FROM mensagem_envio WHERE tipo='aviso_compra_equipe' AND destinatario='bruno@slod.test' AND status='enviado'") === '1', 'produtor recebe o aviso de compra (noreply@)');

  // "Mostrar eventos passados": o evento vira passado (datas no banco) e só aparece com a opção ligada
  sql("UPDATE evento SET data_inicio = UTC_TIMESTAMP() - INTERVAL 3 DAY, data_fim = UTC_TIMESTAMP() - INTERVAL 2 DAY WHERE slug='roda-na-estacao'");
  await V.goto(BASE + '/p/estacao-slod');
  ok(!(await V.content()).includes('Eventos passados'), 'sem a opção, a página do organizador não lista eventos passados');
  await P.goto(BASE + '/painel/organizacao');
  ok((await P.content()).includes('Mostrar eventos passados'), 'opção "Mostrar eventos passados" na organização');
  await P.check('input[type=checkbox][name=mostrar_eventos_passados]');
  await salvar(P);
  ok(sql("SELECT mostrar_eventos_passados FROM organizador WHERE documento='41902878000108'") === '1', 'opção gravada');
  await V.goto(BASE + '/p/estacao-slod');
  const pagOrg = await V.content();
  ok(pagOrg.includes('Eventos passados') && pagOrg.includes('Roda na Estação') && pagOrg.includes('Estação Slod · Belo Horizonte/MG'), 'com a opção, lista o evento passado com o local');

  // desmarcar novidades revoga o consentimento
  await P.goto(BASE + '/painel/preferencias');
  await P.uncheck('[name=receber_novidades]');
  await nav(P, P.click('.acoes-form button'));
  ok(sql("SELECT IF(receber_novidades_em IS NULL, 'revogado', 'ativo') FROM organizador_usuario ou JOIN usuario u ON u.id=ou.usuario_id WHERE u.email='bruno@slod.test'") === 'revogado', 'desmarcar revoga o consentimento');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
