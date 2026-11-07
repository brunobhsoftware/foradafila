// Teste de ponta a ponta da imagem de divulgação do evento (Playwright).
// Rode contra um banco limpo:  BASE=http://127.0.0.1:8000 IMG=/pasta/com/imagens node tests/e2e/fluxo-imagem.js
// IMG precisa ter: capa.png (1600x838), quadrada.jpg, grande.png (> 2 MB) e falsa.png (texto com extensão .png).
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const IMG = process.env.IMG;
const MIDIA = process.env.MIDIA || __dirname + '/../../storage/app/midia/eventos';
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (d, h = '20:00') => new Date(Date.now() + d * 864e5).toISOString().slice(0, 10) + 'T' + h;
const nav = (p, a) => Promise.all([p.waitForNavigation(), a]);
const arquivos = () => (fs.existsSync(MIDIA) ? fs.readdirSync(MIDIA) : []);

(async () => {
  const b = await chromium.launch(); const P = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const erros = []; P.on('pageerror', (e) => erros.push(e.message));

  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Produtor Imagem'], ['email', 'img@prod.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));

  // novo evento com imagem
  await P.goto(BASE + '/painel/eventos/novo');
  ok(await P.locator('text=Clique ou arraste a imagem aqui').count() === 1, 'campo "Clique ou arraste a imagem aqui" no formulário');
  ok((await P.content()).includes('JPEG, GIF ou PNG de até 2 MB') && (await P.content()).includes('1600 x 838'), 'textos de formato, tamanho e dimensão');
  await P.fill('[name=titulo]', 'Samba na Estação'); await P.fill('[name=capacidade]', '200');
  await P.fill('[name=data_inicio]', local(15)); await P.fill('[name=data_fim]', local(16, '01:00'));
  await P.fill('[name="local[nome]"]', 'Estação Slod'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.setInputFiles('input[name=imagem]', IMG + '/capa.png');
  await P.waitForFunction(() => !document.querySelector('.upload__previa').hidden);
  ok((await P.locator('[data-upload-status]').innerText()).includes('1600 x 838'), 'pré-visualização mostra as dimensões');
  await P.check('[name=aceite_cancelamento]'); // declaração de cancelamento (obrigatória)
  await nav(P, P.click('.acoes-form button[type=submit]'));
  ok((await P.content()).includes('Evento criado'), 'evento criado com imagem');
  const src = await P.getAttribute('img.capa-evento', 'src');
  ok(/^\/midia\/eventos\/\d+_[0-9a-f-]{36}\.png\?v=\d+$/.test(src || ''), 'endereço no padrão <id>_<uuid>.png: ' + src);
  const nomeArquivo = src.split('/').pop().split('?')[0];
  ok(arquivos().includes(nomeArquivo), 'arquivo gravado com o nome ' + nomeArquivo);
  const r = await P.request.get(BASE + src);
  ok(r.status() === 200 && r.headers()['content-type'] === 'image/png' && r.headers()['x-content-type-options'] === 'nosniff', 'imagem servida como image/png com nosniff');
  const urlShow = P.url();

  // recusas
  await P.goto(urlShow + '/editar');
  await P.evaluate(() => { document.querySelector('input[name=imagem]').removeAttribute('accept'); });
  for (const [arq, msg] of [['grande.png', 'no máximo 2 MB'], ['falsa.png', 'JPEG, GIF ou PNG']]) {
    await P.goto(urlShow + '/editar');
    // envia direto, sem a conferência do navegador, para testar a do servidor
    await P.evaluate(() => { const i = document.querySelector('input[name=imagem]'); i.parentNode.replaceChild(i.cloneNode(), i); });
    await P.setInputFiles('input[name=imagem]', IMG + '/' + arq);
    await P.check('[name=aceite_cancelamento]'); // declaração de cancelamento (obrigatória)
    await nav(P, P.click('.acoes-form button[type=submit]'));
    ok((await P.content()).includes(msg), `servidor recusa ${arq}`);
  }
  ok(arquivos().includes(nomeArquivo), 'imagem anterior continua após as recusas');

  // troca por JPEG: o PNG antigo some
  await P.goto(urlShow + '/editar');
  await P.setInputFiles('input[name=imagem]', IMG + '/quadrada.jpg');
  await P.waitForFunction(() => document.querySelector('[data-upload-status]').textContent.includes('900 x 900'));
  ok((await P.locator('[data-upload-status]').innerText()).includes('proporção recomendada'), 'aviso de proporção diferente');
  await P.check('[name=aceite_cancelamento]'); // declaração de cancelamento (obrigatória)
  await nav(P, P.click('.acoes-form button[type=submit]'));
  const src2 = await P.getAttribute('img.capa-evento', 'src');
  ok(src2.includes('.jpg') && !arquivos().includes(nomeArquivo) && arquivos().some((f) => f.endsWith('.jpg')), 'troca por JPEG apaga o PNG antigo');

  // página pública e vitrine (publica com lote gratuito)
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  const f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('input[name=cobranca][value=gratuito]').check({ force: true }); await f.locator('[name=quantidade]').fill('100');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  await nav(P, P.click('button:has-text("Publicar evento")'));
  const V = await (await b.newContext()).newPage();
  await V.goto(BASE + '/e/samba-na-estacao');
  ok(await V.locator('img.pub-evento__capa').count() === 1, 'página pública mostra a imagem');
  ok((await V.getAttribute('meta[property="og:image"]', 'content') || '').includes('/midia/eventos/'), 'og:image para compartilhamento');
  await V.goto(BASE + '/eventos');
  ok(await V.locator('img.pub-evento-card__img').count() === 1, 'vitrine mostra a imagem');
  ok((await V.request.get(BASE + '/midia/eventos/..%2F..%2F.env')).status() === 404, 'caminho malicioso em /midia responde 404');

  // remover
  await P.goto(urlShow + '/editar');
  await P.check('[name=remover_imagem]');
  await P.check('[name=aceite_cancelamento]'); // declaração de cancelamento (obrigatória)
  await nav(P, P.click('.acoes-form button[type=submit]'));
  ok(await P.locator('img.capa-evento').count() === 0 && arquivos().length === 0, 'remover apaga a imagem e o arquivo');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
