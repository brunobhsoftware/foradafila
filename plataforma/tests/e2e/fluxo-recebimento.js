// Teste de ponta a ponta do recebimento pela plataforma (V012): explicação no Financeiro, dados para o repasse
// (Pix e/ou conta, validações), "Conectar" em breve e publicação de evento com ingresso pago. Banco limpo + V012:
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-recebimento.js
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
  await P.goto(BASE + '/cadastro');
  for (const [n, v] of [['nome', 'Bruno Recebe'], ['email', 'bruno@recebe.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],
    ['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Estação Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
  for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
  await nav(P, P.click('button[type=submit]'));

  await P.goto(BASE + '/painel/financeiro');
  let t = await P.content();
  ok(t.includes('Como você recebe') && t.includes('dias úteis depois do fim do evento') && t.includes('10 ingressos de R$ 100,00'), 'Financeiro explica como recebe, com exemplo');
  ok(t.includes('Preencha para vender ingressos pagos'), 'sem dados: avisa que falta para vender pago');
  ok(await P.locator('button:has-text("Conectar Mercado Pago")').isDisabled() && t.includes('Em breve'), 'Conectar Mercado Pago em breve (desativado)');

  // Pix escolhido sem chave
  await nav(P, P.click('button:has-text("Salvar dados de recebimento")'));
  ok((await P.content()).includes('Informe a chave Pix'), 'Pix preferido sem chave é recusado');
  // chave CPF inválida
  await P.selectOption('[name=pix_tipo]', 'cpf'); await P.fill('[name=pix_chave]', '111.111.111-11');
  await nav(P, P.click('button:has-text("Salvar dados de recebimento")'));
  ok((await P.content()).includes('CPF inválido'), 'chave CPF inválida é recusada');
  // conta incompleta
  await P.selectOption('[name=pix_tipo]', 'email'); await P.fill('[name=pix_chave]', 'Financeiro@Slod.test');
  await P.fill('[name=banco]', '341 - Itaú Unibanco');
  await nav(P, P.click('button:has-text("Salvar dados de recebimento")'));
  ok((await P.content()).includes('informe banco, agência, conta e favorecido'), 'conta bancária incompleta é recusada');
  await P.fill('[name=agencia]', '1234'); await P.fill('[name=conta]', '12345-x'); await P.fill('[name=favorecido]', 'Estacao Slod Ltda');
  await nav(P, P.click('button:has-text("Salvar dados de recebimento")'));
  t = await P.content();
  ok(t.includes('Dados de recebimento salvos') && t.includes('Pix E-mail: financeiro@slod.test'), 'salvo; repasse vai para o Pix preferido');
  ok(sql("SELECT CONCAT_WS('|', pix_tipo, pix_chave, recebimento_preferido, conta) FROM organizador") === 'email|financeiro@slod.test|pix|12345-X', 'gravado (e-mail minúsculo, conta com dígito maiúsculo)');
  ok(sql("SELECT CONCAT_WS('|', modo, status, ambiente) FROM conta_pagamento WHERE modo='plataforma'") === 'plataforma|ativa|producao', 'conta "Recebimento pela plataforma" ativa');
  ok(t.includes('Pronto para vender ingressos pagos'), 'selo de pronto');

  // Organização não tem mais os campos bancários
  await P.goto(BASE + '/painel/organizacao');
  ok(await P.locator('[name=banco]').count() === 0 && (await P.content()).includes('Financeiro → Dados para o repasse'), 'Organização aponta para o Financeiro');

  // evento com lote pago publica
  await P.goto(BASE + '/painel/eventos/novo');
  await P.fill('[name=titulo]', 'Show Pago');
  await P.fill('[name=data_inicio]', local(15)); await P.fill('[name=data_fim]', local(16, '02:00'));
  await P.fill('[name="local[nome]"]', 'Local'); await P.fill('[name="local[cidade]"]', 'Belo Horizonte'); await P.fill('[name="local[uf]"]', 'MG');
  await P.check('[name=aceite_cancelamento]');
  await nav(P, P.click('.acoes-form button[type=submit]'));
  await nav(P, P.click('button:has-text("Criar tipo")'));
  await P.click('summary:has-text("Adicionar lote")');
  const f = P.locator('form:has(input[name=_form][value^="lote-"])').last();
  await f.locator('[name=nome]').fill('Pista'); await f.locator('[name=quantidade]').fill('50');
  await f.locator('input[name=cobranca][value=pago]').check({ force: true }); await f.locator('[name=preco]').fill('100');
  await nav(P, f.locator('button:has-text("Adicionar lote")').click());
  ok((await P.content()).includes('Dados para o repasse cadastrados em Financeiro'), 'checklist mostra os dados para o repasse');
  await nav(P, P.click('button:has-text("Publicar evento")'));
  ok(sql("SELECT status FROM evento") === 'publicado' && sql("SELECT e.conta_pagamento_id = c.id FROM evento e JOIN conta_pagamento c ON c.modo='plataforma'") === '1', 'evento com ingresso pago publicado, ligado à conta da plataforma');

  // trocar para conta bancária como preferida
  await P.goto(BASE + '/painel/financeiro');
  await P.check('input[name=recebimento_preferido][value=conta]', { force: true });
  await nav(P, P.click('button:has-text("Salvar dados de recebimento")'));
  ok((await P.content()).includes('341 - Itaú Unibanco · ag. 1234 · conta 12345-X'), 'preferência conta bancária');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await b.close();
})();
