// Pessoa convidada para a equipe que cria a senha pelo "Esqueci a senha" consegue entrar (o convite pendente vale como aceito).
//   BASE=http://127.0.0.1:8000 node tests/e2e/fluxo-senha-convite.js (banco limpo; e-mails no laravel.log)
const { chromium } = require('playwright');const {execSync}=require('child_process');const fs=require('fs');const B='http://127.0.0.1:8000';
const sql=(q)=>execSync(`mysql -uroot foradafila -N -e "${q}"`).toString().trim();
(async()=>{const b=await chromium.launch();const P=await b.newPage();
await P.goto(B+'/cadastro');
for (const [n, v] of [['nome', 'Bruno S'], ['email', 'b@senha.test'], ['telefone', '31988887777'], ['senha', 'Senha123'], ['senha_confirmation', 'Senha123'],['documento', '41.902.878/0001-08'], ['razao_social', 'Estacao Slod Ltda'], ['nome_fantasia', 'Slod'], ['email_contato', 'sac@slod.test']]) await P.fill(`[name=${n}]`, v);
for (const t of ['uso_organizador', 'responsabilidade_organizador', 'privacidade']) await P.check(`[name="aceites[${t}]"]`);
await Promise.all([P.waitForNavigation(),P.click('button[type=submit]')]);
await P.goto(B+'/painel/equipe');await P.fill('form:has(button:has-text("Gerar convite")) [name=nome]','Ana Gerente');await P.fill('form:has(button:has-text("Gerar convite")) [name=email]','ana@gerente.test');
await Promise.all([P.waitForNavigation(),P.click('button:has-text("Gerar convite")')]);
const ini=fs.statSync('storage/logs/laravel.log').size;
const Q=await (await b.newContext()).newPage();await Q.goto(B+'/esqueci-a-senha/produtor');
await Q.fill('[name=email]','ana@gerente.test');await Promise.all([Q.waitForNavigation(),Q.press('[name=email]','Enter')]);
await Q.waitForTimeout(1000);
const log=fs.readFileSync('storage/logs/laravel.log').subarray(ini).toString().replace(/=\r?\n/g,'').replace(/=3D/g,'=');
const link=(log.match(/http:\/\/127\.0\.0\.1:8000\/nova-senha\/produtor\/[^\s"<]+/)||[])[0];
await Q.goto(link.replace(/&amp;/g,'&'));await Q.fill('[name=senha]','NovaSenha1');await Q.fill('[name=senha_confirmation]','NovaSenha1');
await Promise.all([Q.waitForNavigation(),Q.press('[name=senha_confirmation]','Enter')]);
await Q.fill('[name=email]','ana@gerente.test');await Q.fill('[name=senha]','NovaSenha1');await Promise.all([Q.waitForNavigation(),Q.press('[name=senha]','Enter')]);
console.log('URL após login:', Q.url(), '| vínculo:', sql("SELECT ou.status FROM organizador_usuario ou JOIN usuario u ON u.id=ou.usuario_id WHERE u.email='ana@gerente.test'"));
await b.close();})();
