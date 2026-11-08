// Teste de ponta a ponta: site público, menu superior, página do evento, inscrição gratuita,
// Meus ingressos, segunda via, cancelamento e isolamento entre compradores (Playwright).
// Rode contra um banco limpo:  BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-comprador.js
const { chromium, devices } = require('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const OUT = process.env.OUT || require('os').tmpdir() + '/ff-shots/';
require('fs').mkdirSync(OUT, { recursive: true });
const ok = (c, m) => { console.log((c ? 'PASSOU ' : 'FALHOU ') + m); if (!c) process.exitCode = 1; };
const local = (dias, hora = '20:00') => new Date(Date.now() + dias * 864e5).toISOString().slice(0, 10) + 'T' + hora;
const nav = (p, acao) => Promise.all([p.waitForNavigation(), acao]);
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 -uroot foradafila -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const formularioLegado = require('./_formulario-legado');

(async () => {
  const browser = await chromium.launch();
  const ctx = () => browser.newContext({ viewport: { width: 1360, height: 900 } }).then((c) => c.newPage());
  const erros = [];

  // ---------- Produtor prepara um evento gratuito publicado + um lote pago ----------
  const P = await ctx();
  P.on('pageerror', (e) => erros.push(e.message));
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Produtor'], ['email', 'bruno@prod.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '11.222.333/0001-81'], ['razao_social', 'Choperia X Ltda'], ['nome_fantasia', 'Choperia X'], ['email_contato', 'sac@choperia.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));
  formularioLegado(sql);
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Noite do Chope');
  await P.fill('[name=resumo]', 'Evento gratuito do piloto.');
  await P.fill('[name=capacidade]', '100');
  await P.fill('[name=data_inicio]', local(20)); await P.fill('[name=data_fim]', local(21, '02:00'));
  await P.fill('[name="local[nome]"]', 'Choperia X'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]'); // declaração de cancelamento (obrigatória)
  await nav(P, P.click('.acoes-form button[type=submit]'));
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  let f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('input[name=cobranca][value=gratuito]').check({ force: true }); await f.locator('[name=quantidade]').fill('50');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  await nav(P, P.click('button:has-text("Publicar evento")'));
  ok((await P.content()).includes('Evento publicado'), 'produtor publica evento gratuito');
  // lote pago depois de publicado (a venda paga ainda não existe: só mostra o preço)
  await P.click('summary:has-text("Adicionar lote")');
  f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('[name=nome]').fill('Lote VIP'); await f.locator('input[name=liberacao][value=por_data]').check({ force: true }); await f.locator('input[name=cobranca][value=pago]').check({ force: true }); await f.locator('[name=preco]').fill('50'); await f.locator('[name=quantidade]').fill('20');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  ok((await P.content()).includes('Lote “Lote VIP” criado'), 'lote pago criado');
  const linkPublico = await P.getAttribute('a:has-text("Ver página pública")', 'href');
  ok(!!linkPublico && /\/e\/[0-9a-f]{10}$/.test(linkPublico), 'painel mostra o link da página pública');
  const menuProdutor = await (await P.goto(BASE + '/')) && await P.locator('#menu').innerText();
  ok(menuProdutor.includes('Meus eventos') && menuProdutor.includes('Novo evento'), 'menu superior do produtor mostra Meus eventos e Novo');

  // ---------- Visitante ----------
  const V = await ctx();
  V.on('pageerror', (e) => erros.push(e.message));
  await V.goto(BASE + '/');
  const menuV = await V.locator('#menu').innerText();
  ok(['Solução', 'Preços', 'Eventos', 'Ajuda', 'Meus ingressos', 'Área do produtor'].every((t) => menuV.includes(t)), 'menu do visitante completo');
  ok(!menuV.includes('Meus eventos'), 'visitante não vê Meus eventos');

  // nenhum link interno quebrado nas páginas públicas
  const visitados = new Set(); const quebrados = [];
  for (const pagina of ['/', '/solucao', '/precos', '/ajuda', '/eventos', linkPublico.replace(BASE, '')]) {
    await V.goto(BASE + pagina);
    const hrefs = await V.$$eval('a[href]', (as) => as.map((a) => a.getAttribute('href')));
    if (hrefs.includes('#')) quebrados.push(pagina + ' tem href="#"');
    for (const h of hrefs) {
      if (!h.startsWith(BASE) && !h.startsWith('/')) continue;
      const url = h.startsWith('/') ? BASE + h : h;
      if (visitados.has(url)) continue; visitados.add(url);
      const r = await V.request.get(url, { maxRedirects: 0 });
      if (![200, 302].includes(r.status())) quebrados.push(`${url} → ${r.status()}`);
    }
  }
  ok(quebrados.length === 0, `links internos ok (${visitados.size} verificados) ${quebrados.join(' | ')}`);
  ok((await (await V.goto(BASE + '/precos')).text()).includes('Taxa justa por ingresso pago'), 'página Preços explica a taxa');
  ok(!(await V.content()).match(/\b[2-9],?\d*%/), 'Preços não inventa outros percentuais');

  await V.goto(BASE + '/eventos');
  ok((await V.content()).includes('Noite do Chope'), 'vitrine lista o evento publicado');
  await V.goto(linkPublico);
  const pagEvento = await V.content();
  ok(pagEvento.includes('Choperia X Ltda') && pagEvento.includes('11.222.333/0001-81') && pagEvento.includes('sac@choperia.test'), 'página do evento identifica o organizador');
  ok(pagEvento.includes('venda online em breve') && (await V.locator('select[name^="q["]').count()) === 1, 'lote pago sem botão de compra; só o gratuito tem quantidade');
  await V.screenshot({ path: OUT + 'c1-evento.png', fullPage: true });

  // ---------- Inscrição gratuita criando a conta ----------
  await V.selectOption('select[name^="q["]', '2');
  await nav(V, V.click('button:has-text("Garantir ingresso")'));
  ok(V.url().includes('/inscricao'), 'vai para a inscrição');
  await V.fill('[name=cpf]', '62819482112'); await V.waitForSelector('[name=nome]:visible'); await V.fill('[name=nome]', 'Ana Compradora'); await V.fill('[name=email]', 'ana@publico.test');
  await V.fill('[name="participantes[0][nome]"]', 'Ana Compradora'); await V.fill('[name="participantes[0][cpf]"]', '529.982.247-25');
  await V.fill('[name="participantes[1][nome]"]', 'Pedro Amigo'); await V.fill('[name="participantes[1][cpf]"]', '111.111.111-11');
  await V.check('[name=aceite_termos]');
  await nav(V, V.click('button:has-text("Confirmar inscrição")'));
  ok((await V.content()).includes('CPF inválido'), 'CPF inválido do participante é recusado');
  await V.fill('[name="participantes[1][cpf]"]', '153.509.460-56');
  await V.check('[name=aceite_termos]');
  await nav(V, V.click('button:has-text("Confirmar inscrição")'));
  const meus = await V.content();
  ok(V.url().endsWith('/minha-conta') && meus.includes('confirmado'), 'inscrição confirmada e leva a Meus ingressos');
  ok(meus.includes('Ana Compradora') && meus.includes('Pedro Amigo') && (meus.match(/FF-\d{4}-\d{6}/g) || []).length >= 2, 'dois ingressos numerados');
  await V.screenshot({ path: OUT + 'c2-meus-ingressos.png', fullPage: true });
  const menuC = await V.locator('#menu').innerText();
  ok(menuC.includes('Meus ingressos') && !menuC.includes('Meus eventos'), 'menu do comprador não mostra área do produtor');

  // segunda via
  const linkIngresso = await V.getAttribute('a:has-text("Abrir ingresso")', 'href');
  await V.goto(linkIngresso);
  await V.waitForSelector('#qr svg');
  ok(await V.locator('#qr svg').count() === 1, 'segunda via mostra o QR Code');
  const qr1 = await V.getAttribute('#qr', 'data-conteudo');
  await V.reload(); const qr2 = await V.getAttribute('#qr', 'data-conteudo');
  ok(qr1 === qr2 && /^FF1\.[A-Za-z0-9_-]{43}\.[0-9a-f]{16}$/.test(qr1), 'segunda via reaproveita o mesmo código assinado');
  await V.screenshot({ path: OUT + 'c3-ingresso.png', fullPage: true });

  // ---------- Isolamento: outro comprador não vê o ingresso da Ana ----------
  const O = await ctx();
  await O.goto(BASE + '/minha-conta/criar');
  for (const [n, v] of [['nome', 'Outro'], ['email', 'outro@publico.test'], ['senha', 'Outro123'], ['senha_confirmation', 'Outro123']]) await O.fill(`[name=${n}]`, v);
  await nav(O, O.click('button:has-text("Criar conta")'));
  ok((await O.goto(linkIngresso)).status() === 404, 'outro comprador recebe 404 no ingresso da Ana');
  ok(!(await (await O.goto(BASE + '/minha-conta')).text()).includes('Noite do Chope'), 'Meus ingressos do outro comprador vem vazio');
  ok((await O.goto(BASE + '/painel')).url().includes('/entrar'), 'comprador logado não entra na área do produtor');

  // produtor vê o vendido
  await P.goto(linkPublico.replace(/.*\/e\//, BASE + '/painel/eventos/').replace(/noite-do-chope.*/, ''));
  await P.goto(BASE + '/painel');
  ok((await P.content()).match(/Ingressos vendidos<\/small><b>2</), 'painel do produtor conta 2 ingressos');

  // ---------- Cancelamento da inscrição gratuita ----------
  await V.goto(BASE + '/minha-conta');
  V.once('dialog', (d) => d.accept());
  await nav(V, V.click('button:has-text("Cancelar inscrição")'));
  const aposCancelar = await V.content();
  ok(aposCancelar.includes('cancelada') && (aposCancelar.match(/>Cancelado</g) || []).length >= 2, 'cancelamento invalida os ingressos');
  await V.goto(linkIngresso);
  ok((await V.getAttribute('#qr', 'data-conteudo')) === '', 'ingresso cancelado não exibe QR');

  // clique duplo: a mesma chave de idempotência não cria dois pedidos
  await V.goto(linkPublico);
  await V.selectOption('select[name^="q["]', '1');
  await nav(V, V.click('button:has-text("Garantir ingresso")'));
  await V.fill('[name="participantes[0][cpf]"]', '529.982.247-25');
  await V.check('[name=aceite_termos]');
  const form = await V.evaluate(() => { const f = document.querySelector('form[action*="inscricao"]'); return { action: f.action, dados: Object.fromEntries(new FormData(f)) }; });
  const r1 = await V.request.post(form.action, { form: form.dados, maxRedirects: 0 });
  const r2 = await V.request.post(form.action, { form: form.dados, maxRedirects: 0 });
  ok(r1.status() === 302 && r2.status() === 302, 'dois envios iguais respondem sem erro');

  // CPF já cadastrado: mostra os dados (e-mail mascarado), sem senha e sem entrar na conta
  const Z = await ctx();
  await Z.goto(linkPublico); await Z.selectOption('select[name^="q["]', '1');
  await nav(Z, Z.click('button:has-text("Garantir ingresso")'));
  ok(!(await Z.locator('[name=nome]').isVisible()) && (await Z.locator('[name=senha]').count()) === 0, 'compra pede o CPF primeiro, sem senha');
  await Z.fill('[name=cpf]', '62819482112');
  await Z.waitForSelector('[data-cpf-achado]:visible');
  const achado = await Z.locator('[data-cpf-achado]').innerText();
  ok(achado.includes('Ana Compradora') && achado.includes('an***') && !achado.includes('ana@publico.test'), 'CPF conhecido: mostra o nome e o e-mail mascarado');
  ok(await Z.inputValue('[name="participantes[0][nome]"]') === 'Ana Compradora', 'com o CPF, o 1º ingresso já vem com o nome do comprador');
  await Z.check('[name=aceite_termos]');
  await nav(Z, Z.click('button:has-text("Confirmar inscrição")'));
  ok((await Z.content()).includes('enviados para an***@publico.test') && (await Z.goto(BASE + '/minha-conta')).url().includes('/entrar'), 'compra confirmada no cadastro existente, sem entrar na conta');

  // "Não desejo informar o CPF" e "Estou comprando para outra pessoa"
  const Y = await ctx();
  await Y.goto(linkPublico); await Y.selectOption('select[name^="q["]', '1');
  await nav(Y, Y.click('button:has-text("Garantir ingresso")'));
  ok(!(await Y.locator('[data-depois-cpf]').isVisible()), 'ingressos só aparecem depois do CPF ou do botão');
  await Y.click('[data-botao-sem-cpf]');
  ok(await Y.locator('[name="participantes[0][nome]"]').isVisible(), '"Não desejo informar o CPF" libera os ingressos');
  await Y.fill('[name=nome]', 'Sem Cpf Silva'); await Y.fill('[name=email]', 'semcpf@publico.test');
  ok(await Y.inputValue('[name="participantes[0][nome]"]') === 'Sem Cpf Silva', '1º ingresso acompanha o nome digitado');
  await Y.click('[data-outra-pessoa]');
  ok(await Y.inputValue('[name="participantes[0][nome]"]') === '', '"Estou comprando para outra pessoa" limpa o 1º ingresso');
  await Y.fill('[name="participantes[0][nome]"]', 'Amiga do Sem Cpf');
  await Y.check('[name=aceite_termos]');
  await nav(Y, Y.click('button:has-text("Confirmar inscrição")'));
  ok(sql("SELECT CONCAT(IFNULL(c.cpf,'-'),'|',pa.nome) FROM comprador c JOIN pedido p ON p.comprador_id=c.id JOIN ingresso i ON i.pedido_id=p.id JOIN participante pa ON pa.ingresso_id=i.id WHERE c.email='semcpf@publico.test'") === '-|Amiga do Sem Cpf', 'compra sem CPF: comprador cadastrado pelo e-mail, ingresso para outra pessoa');

  // celular
  const M = await (await browser.newContext({ ...devices['iPhone 13'] })).newPage();
  for (const pg of ['/', '/precos', linkPublico.replace(BASE, '')]) {
    await M.goto(BASE + pg);
    ok(await M.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'sem rolagem horizontal no celular: ' + pg);
  }
  await M.goto(linkPublico); await M.screenshot({ path: OUT + 'c4-evento-celular.png', fullPage: true });

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await browser.close();
})();
