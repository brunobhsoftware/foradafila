# Fora da Fila — contexto do projeto

Plataforma SaaS de venda de ingressos e check-in para eventos (foradafila.com.br).
Dono do projeto: Bruno (BH Software), desenvolvedor com experiência em ASP.NET MVC.
Idioma do projeto, da documentação e das conversas: **português do Brasil**.

## Onde está cada coisa

| Arquivo | Conteúdo |
| --- | --- |
| `docs/regras-de-negocio.md` | Regras de negócio completas (17 seções). **Fonte da verdade** para modelagem e implementação. |
| `docs/requisitos-originais.md` | Orientação original do Bruno, na íntegra |
| `plataforma/` | **Aplicação Laravel 12** (site público + área do produtor). Ver seção "Plataforma (Laravel)" |
| `docs/identidade-visual/` | Logos, manual de marca e layout de referência da página inicial |
| `banco/V001__criacao_foradafila.sql` | Script de criação do banco (revisão 2, 1.462 linhas): 54 tabelas, 3 views, 4 triggers, dados iniciais |
| `banco/V002__termos_iniciais.sql` | Termos provisórios 0.1 (uso, responsabilidade, compra, privacidade). Idempotente |
| `banco/V003__ajuste_modelo_confirmacao.sql` | Só dados: tira "em anexo" do modelo padrão de confirmação. Idempotente |
| `banco/V005__evento_capacidade_opcional.sql` | `evento.capacidade` aceita NULL (capacidade máxima opcional). Idempotente |
| `banco/V006__integracao_pagamentos.sql` | Campos para Mercado Pago e outras operadoras (OAuth, checkout, idempotência, webhooks). Idempotente. Importado em produção em 07/11/2026 ("V006 ok") |
| `banco/V007__administrador_plataforma.sql` | Só dados: cria o administrador da plataforma (Bruno, bruno@bhsoftware.com.br, `admin_plataforma = 1`, sem senha). Idempotente. Importado em produção em 07/11/2026 ("V007 ok") |
| `docs/integracao-pagamentos.md` | Estudo da integração (Mercado Pago 1º): fluxos, mapeamento de campos, homologação, `.env` `MP_*` |
| `banco/V004__organizador_dados_bancarios_logo_preferencias.sql` | Representante legal, dados bancários, chave da API (reservada), "mostrar eventos passados" e preferências de notificação. Idempotente |

O documento vivo (editável e comentável) continua em
https://claude.ai/artifact/SdhmCrhNMcPWmupBJZKv6b. `docs/regras-de-negocio.md` é uma exportação de 07/11/2026
(o diagrama dos 12 módulos não foi exportado).

## Identidade visual

- Cores: Tinta `#101828`, Laranja `#F25C05`, Amarelo `#FFD60A`, Branco `#FFFFFF`.
- Fontes: Space Grotesk Bold (títulos) e Inter Regular (textos).
- Logo: símbolo de 4 pessoas na fila + um espaço tracejado + pessoa laranja que saiu da fila, com 3 raios amarelos;
  nome "fora da fila" minúsculo com ponto laranja. Logo alternativa (pessoa com ingresso) em `docs/identidade-visual/`.

## Modelo de negócio (resumo)

- O Fora da Fila é **só prestador de tecnologia**: o dinheiro do ingresso cai direto na conta do organizador no
  gateway (Mercado Pago na fase 1; PayPal e PagBank previstos). A plataforma recebe só a comissão.
- **Sem taxa para o comprador.** Comissão por ingresso: `max(valor_pago × % + fixo, mínimo)`.
- Dois modos de cobrança ainda em estudo: `split` (operadora retém e repassa) ou `fatura` mensal.
  Fatura vencida → organizador `bloqueado_debito` → não cria nem publica evento (trigger).
- O organizador é o responsável legal pelo evento; aceite de termos versionados obrigatório antes de publicar.
- LGPD: organizador = controlador, Fora da Fila = operador.

## Banco de dados (produção)

- MariaDB 11.8.9 na hospedagem web Hostinger. Banco `u839930205_foradafila`, usuário `u839930205_bhsoftware`.
- **V001 já importado em produção em 07/11/2026** e conferido (54 tabelas, 3 views, 4 triggers, seeds).
- **V002 (termos) importado em produção** e conferido em 07/11/2026 (4 termos 0.1 vigentes).
- Senha do usuário do banco trocada em 07/11/2026 por uma gerada (só existe no `.env.foradafila` do servidor).
- A aplicação conecta em `127.0.0.1:3306`. O host `*.hstgr.io` é só para acesso remoto liberado por IP.
- Senha nunca no código nem no Git: fica no `.env` do servidor, fora do `public_html`.
- Toda mudança de estrutura entra como nova migração versionada (`V002__...`), nunca por edição manual no phpMyAdmin.
- Convenções: InnoDB, utf8mb4, datas em UTC, dinheiro `DECIMAL(12,2)`, `id BIGINT` interno + `uuid` público,
  multi-tenant por `organizador_id` em toda tabela de negócio, exclusão lógica para dados financeiros.
- Triggers: `trg_conta_bu`, `trg_evento_bi`, `trg_evento_bu`, `trg_lote_bu`.
- **V003 e V004 importados em produção** em 07/11/2026 (conferido: "V004 ok" e "preferencias ok").
- **V005 importado em produção** em 07/11/2026 ("V005 ok"): capacidade máxima do evento é opcional.
  O código continua tolerando banco sem o V004 (`App\\Support\\Esquema::temV004()`).
- **phpMyAdmin da Hostinger:** nunca misturar consultas a `information_schema` com comandos no mesmo envio — a
  sessão passa a usar esse banco e tudo dá `#1044 acesso negado`. Se acontecer, reabrir o phpMyAdmin e começar o
  script com `USE u839930205_foradafila;` e nomes qualificados (`u839930205_foradafila.tabela`).

## Plataforma (Laravel)

- Laravel 12, PHP 8.3, Blade + CSS próprio (`public/css/app.css`) + JS puro (`public/js/app.js`). **Sem Vite/npm.**
- Sem migrations do Laravel: as tabelas vêm dos scripts `banco/V0xx`. Sessão, cache e fila em arquivo/sync.
- Auth no model `Usuario` (tabela `usuario`, coluna `senha_hash`). Login bloqueia 15 min após 5 erros.
- Multi-organizador: `App\Support\Tenant` + trait `PertenceAoOrganizador` (escopo global, **falha fechada**: sem
  organizador definido a consulta não retorna nada). O middleware `organizador` roda antes do route model binding.
- Permissões `modulo.acao` vêm de `perfil_permissao`; use `@can('evento.publicar')` / `Gate::authorize(...)`.
  Vínculo com `todos_eventos = 0` limita aos eventos de `organizador_usuario_evento` (`Evento::visiveis()`).
- Recusas dos triggers (SQLSTATE 45000) viram erro de formulário via `App\Support\RegraDoBanco::executar()`.
- Toda ação relevante grava em `auditoria` via `App\Support\Auditoria::registrar()`.
- Datas: banco em UTC; tela em `America/Sao_Paulo` (helpers `data_local`, `para_input_data`, `utc_de_local`).
- Telas prontas (produtor): cadastro, login, convite da equipe, troca de organização, painel, **Meus eventos**
  (lista, novo, editar, publicar/pausar/encerrar/cancelar, link da página pública), tipos de ingresso e lotes,
  equipe, níveis de acesso, dados da organização. Os demais módulos do menu lateral mostram "em breve".
- Site público (`layouts/publico` + `site/_topo`, `site/_rodape`, CSS `public/assets/css/publico.css`):
  início, `/solucao`, `/precos`, `/ajuda`, `/eventos` (vitrine), `/e/{slug}` (evento), `/p/{slug}` (organizador),
  `/termos/{tipo}`. Menu superior muda conforme quem está logado (produtor e/ou comprador).
- Comprador: guard **`comprador`** (tabela `comprador`), separado do produtor. Rotas em `/minha-conta`:
  entrar, criar conta, Meus ingressos, segunda via (`/ingressos/{uuid}`, QR no navegador com
  `public/js/vendor/qrcode-generator.js`, MIT) e cancelamento de inscrição gratuita.
- **Inscrição gratuita real** (`InscricaoController`): só lotes com preço 0. Baixa de estoque por UPDATE condicional,
  numeração por `evento.ultimo_numero_ingresso`, `codigo_qr` 256 bits + HMAC (`Ingresso::conteudoQr()`),
  participante por ingresso, aceite de `compra_comprador` e `privacidade`, chave de idempotência.
  Lotes pagos aparecem com preço e "venda online em breve" — sem botão de compra.
- Sem cron ainda: lote `agendado` abre quando chega `venda_inicio`; lote com `lote_anterior_id` só abre quando o
  anterior esgota, encerra ou passa do fim (`EventoPublicoController::loteAVenda`).
- **Imagem de divulgação do evento** (opcional): JPEG/GIF/PNG até 2 MB, recomendada 1600 x 838, campo "clique ou
  arraste" (`components/upload-imagem`). Arquivo `eventos/<id do evento>_<uuid do evento>.<ext>` no disco `midia`
  (`FF_ARMAZENAMENTO`, em produção `/home/u839930205/domains/foradafila.com.br/armazenamento`, fora do public_html),
  servido por `/midia/eventos/{arquivo}` (`MidiaController`). `evento.imagem_capa_url` guarda `/midia/...?v=<data>`.
  Trocar de formato apaga a versão antiga; "remover" apaga o arquivo. Aparece no painel, na página do evento
  (com `og:image`) e na vitrine. Lógica em `App\Support\ImagemEvento`.
- **Organização (V004):** representante legal e CPF são **obrigatórios**; banco, agência, conta e favorecido são
  **opcionais**, mas se um for preenchido os quatro são pedidos (decisão do Bruno, 07/11/2026). Logomarca opcional (JPEG/GIF/PNG até 2 MB, recomendada
  136x136) em `logomarcas/<nome-da-organizacao>_<uuid>.<ext>` no disco `midia` (`App\\Support\\Logomarca`), exibida no
  menu do painel, na página do evento e na do organizador. Opção "Mostrar eventos passados" na página pública.
  **Chave de acesso da API: campo bloqueado** (decisão do Bruno); colunas `api_chave_*` reservadas para o futuro
  botão "gerar token de integração" (guardar só o hash SHA-256).
- **Capacidade máxima do evento é opcional** (com o V005): em branco, a quantidade de ingressos é livre e a lotação é
  responsabilidade da empresa; com capacidade, a soma dos lotes continua limitada (app e trigger).
- **Cadastro de ingressos (lotes):** nome, quantidade, interruptor "Ingresso ativo" (desativado = status `pausado`,
  também pelo botão Ativar/Desativar da lista, `lotes.alternar`), **Gratuito/Pago** (pago pede valor > 0 e
  modalidade inteira/meia), **Por data** (início e fim) ou **Por lote** (escolhe o lote anterior; começa quando ele
  esgota, encerra ou passa do fim; recusa dependência circular) e **Quem pode comprar**: `publico` = todo o público,
  `convidados` = restrito a convidados (link por e-mail/WhatsApp, futuro), `oculto` = **adicionado manualmente** pela
  equipe (o enum do banco não mudou; só o significado de `oculto`). Só `publico` aparece na página do evento.
  Lote pago só publica com conta de recebimento ativa (Mercado Pago, próxima fase).
- **Financeiro / Mercado Pago (OAuth pronto, aguardando a aplicação no MP):** `/painel/financeiro` (`FinanceiroController`)
  conecta a conta do organizador por OAuth com PKCE (`state` na sessão), grava em `conta_pagamento` (status `conectada`,
  tokens criptografados com a APP_KEY, e-mail/apelido/país via `/users/me`) e desconecta (apaga tokens, `revogada`).
  Sem `MP_CLIENT_ID`/`MP_CLIENT_SECRET` no `.env`, o botão fica desativado com aviso. Cliente em `App\Pagamentos\MercadoPago`;
  `config/pagamentos.php` (`MP_API_URL`/`MP_AUTORIZACAO_URL` só para teste). **Webhook** `POST /webhooks/mercadopago`
  (sem CSRF, throttle `webhook-mp`): confere `x-signature` com `MP_WEBHOOK_SECRET`, grava em `webhook_evento`
  (`recebido` ou `ignorado`), não duplica, sempre 200; o processamento entra com o checkout. Homologação, checkout,
  renovação de token e reembolso: ainda não feitos. A tela mostra 3 etapas (criar a aplicação — com os endereços para copiar e o botão
  para https://www.mercadopago.com.br/developers/panel/app —, conectar a conta, homologar). A etapa 1 tem antes o caminho
  "Com ajuda de IA, em 2 passos" (`claude plugin install mercadopago` + prompt com os endereços, para copiar).
- **Administração da plataforma** (`/admin`, link "Administração" no rodapé; middleware `admin` = `usuario.admin_plataforma`,
  os demais recebem 404): início com números gerais e **Mercado Pago** (`admin/mercadopago`): prompt de IA que cria a
  aplicação e devolve `MP_CLIENT_ID`/`MP_CLIENT_SECRET`/`MP_WEBHOOK_SECRET`, endereços para copiar, botão do painel de
  desenvolvedores e formulário das chaves. Chaves em `parametro_sistema` (`mp_client_id`; `mp_client_secret` e
  `mp_webhook_secret` criptografados com a APP_KEY; em branco mantém); o `.env` é só reserva
  (`MercadoPago::credencial()`). **PKCE opcional** (`mp_pkce`, padrão desligado; cartão 3 da página): tem de estar igual
  na aplicação do Mercado Pago (Detalhes da aplicação → Editar). **Pagamento de teste** (cartão 4, padrão R$ 5): token da própria
  aplicação (client_credentials, cache 5 h) + preferência do Checkout Pro com webhook; retorno `admin/mercadopago/teste/retorno` mostra
  payment_id, merchant_order_id e status conferido na API; a página lista os 10 últimos webhooks. **Validado em produção em
  07/11/2026:** pagamento de R$ 5 por Pix aprovado (payment_id 187277190645, merchant_order_id 45909646019). O pagamento de teste
  tem ambiente **Teste** (padrão; usa o Access Token de teste `mp_teste_access_token` ou, sem ele, client_credentials com
  `mp_teste_client_id`/`mp_teste_client_secret` — `MercadoPago::tokenTeste()`; segredos criptografados — exigido pela Qualidade da
  integração; pagar com a conta de teste compradora) ou **Produção** (token client_credentials). A página tem dois blocos completos:
  **"1. Ambiente de TESTE"** e **"2. Ambiente de PRODUÇÃO"**, cada um com os 4 campos (Client ID, Client Secret, Access Token,
  assinatura dos webhooks), salvar, "Verificar de quem é o token" e o botão de pagamento (teste / de verdade). Access Token de
  produção (`mp_access_token`, opcional) tem prioridade sobre o client_credentials. Webhook: mesma URL; aceita a
  assinatura de produção ou a de teste (`mp_teste_webhook_secret`, opcional) — a lista mostra "Válida (teste)". O botão do pagamento
  de teste não redireciona: mostra o link (copiar + QR Code) para abrir em janela anônima/celular, sem a conta da BH Software logada
  (o MP recusa misturar conta real e de teste). Botão "Verificar de quem são os tokens" (`/users/me` com cada token: conta, apelido,
  conta de teste ou real; avisa se os dois são da mesma conta). O retorno `/admin/mercadopago/teste/retorno` é aberto (sem login só mostra os IDs). Empresas e taxas/isenções: em breve. Administrador sem empresa entra direto em `/admin`.
  **Primeiro acesso:** administrador criado sem senha usa "Esqueci a senha" (link de 60 min, `SenhaController::podeRedefinir`).
  Modelo decidido pelo Bruno (07/11/2026): **aplicação única da BH Software + "Conectar" de cada produtor**; o dinheiro
  cai direto na conta do produtor. O Financeiro do produtor mostra a etapa 1 só como "Liberação pela plataforma".
- **Política de cancelamento:** ao criar/editar evento o produtor marca a declaração
  `EventoController::DECLARACAO_CANCELAMENTO` (obrigatória), registrada em `auditoria`
  (`evento.declaracao_cancelamento`, com IP e texto). A página pública sempre mostra que devolver todos os valores
  é responsabilidade do produtor, sob as leis de defesa do consumidor. Termos gerais (0.1) não foram alterados.
- **Política de cancelamento pré-preenchida:** evento novo já vem com o modelo `App\\Support\\PoliticaCancelamento::modelo()`
  (CDC art. 49 — arrependimento de 7 dias; devolução integral se o organizador cancelar; alteração de data/local;
  contatos da organização), com razão social, CNPJ/CPF, e-mail e WhatsApp. Botão "Usar texto padrão" na edição.
  Modelo sugerido, **não validado juridicamente** — revisar com advogado.
- **Cadastro de evento:** ao lado do formato (presencial/híbrido) há o botão "Usar endereço da organização", que
  preenche nome do local e endereço com os dados de Organização (pergunta antes de substituir; desativado se a
  organização não tiver endereço). Só no navegador: o endereço continua editável e é gravado no `local` do evento.
- **Datas do evento:** ao escolher o início, término e abertura dos portões recebem a mesma data (vazios: mesmo horário
  do início; preenchidos: mantêm horário e a diferença de dias, ex. virada para o dia seguinte) — só no navegador
  (`public/js/app.js`). "Exigir CPF do participante" vem **desmarcado** em evento novo (decisão do Bruno, 07/11/2026).
- **"Mostrar eventos passados"** aparece em Organização e em Preferências (mesmo campo da organização; só quem tem
  `organizador.editar` altera).
- **Perfil "Dono" aparece como "Produtor"** em todas as telas (accessor em `PerfilAcesso`); o código interno continua
  `dono` e o banco não foi alterado. Mensagens falam em "Produtor".
- **Trocar senha e trocar e-mail** (`/painel/minha-conta/senha` e `/email`, botões em Organização e "Trocar senha" no
  menu): código de 6 dígitos por e-mail (fique@), só o hash no cache, 15 min, 5 tentativas, uso único, reenvio a cada
  60 s (`App\\Support\\CodigoVerificacao`). O e-mail só troca depois de validar o código no e-mail **atual**; avisos de
  segurança (noreply@) vão para o e-mail antigo e o novo. O código não fica em `mensagem_envio`.
- **Menu lateral enxuto:** só os módulos. Empresa (logo, nome, e-mail de contato), Minhas preferências, Trocar senha,
  Minha página de eventos (`/p/{slug}`, nova aba), Trocar de organização, Ir para o site e Sair ficam no **menu da conta**
  no alto de todo o painel (`layouts/conta-menu.blade.php`, `<details>`; fecha ao clicar fora/Esc) e no cartão
  "Sua organização" do Dashboard. Cartões da vitrine e de `/p/{slug}` mostram "A partir de R$ ..." ou "Gratuito"
  (menor preço dos lotes públicos ativos/agendados, `EventoPublicoController::precosMinimos`).
- **Equipe:** botão "Reenviar convite" na linha de cada convite pendente (novo link, o anterior deixa de valer).
- **Limites de tentativas (throttle):** sempre com nome próprio (`throttle:5,15,codigo-senha`), senão rotas diferentes
  dividem o mesmo contador.
- **Preferências de notificação** (por pessoa da equipe, `organizador_usuario`): `/painel/preferencias` —
  "Notificar-me cada vez que um participante realizar uma compra" (aviso por noreply@) e "Quero receber as
  novidades do Fora da Fila por e-mail" (consentimento com data em `receber_novidades_em`).
- `config/foradafila.php`: `comprador_cancela_gratuito` (padrão true; `FF_COMPRADOR_CANCELA_GRATUITO`).
- Testes de ponta a ponta: `tests/e2e/fluxo-produtor.js` (22), `fluxo-comprador.js` (30) e `fluxo-imagem.js` (18), `fluxo-organizacao.js` (30) e `fluxo-conta.js` (38) e `fluxo-evento-regras.js` (19, simula antes/depois do V005), `fluxo-endereco.js` (13), `fluxo-politica.js` (9), `fluxo-ingressos.js` (19), `fluxo-admin.js` (33, porta 8001), `fluxo-financeiro.js` (19, com Mercado Pago falso na porta 8099 e o Laravel em 8001 com as variáveis `MP_*` de teste do cabeçalho do arquivo) — os três últimos precisam do V004 —, com Playwright,
  contra banco limpo e `php artisan serve`.
- Desenvolvimento local: MariaDB com V001+V002, `.env` local, `php artisan serve`. O `vendor/` vem da branch
  `publicacao` (o proxy da nuvem bloqueia downloads do Composer): `git archive origin/publicacao vendor | tar -x -C plataforma`.

## E-mail (definido pelo Bruno em 07/11/2026)

- **Todo envio de e-mail usa as contas SMTP da Hostinger do domínio** (smtp.hostinger.com, SSL, porta 465):
  - `fique@foradafila.com.br` — confirmações de cadastro, envio de ingressos, convites, senha e todas as demais
    operações com o usuário. Mailer `fique` (padrão). Use `App\Support\Email::operacao(...)`.
  - `noreply@foradafila.com.br` — mensagens sem resposta (avisos automáticos). Mailer `noreply`.
    Use `App\Support\Email::aviso(...)`. A caixa tem resposta automática configurada.
  - Existe também `atendimento@foradafila.com.br` (caixa que pega tudo do domínio) — ainda não usada pela plataforma.
  Em produção há uma conta de comprador de teste com esse e-mail ("Teste de e-mail Fora da Fila"), usada para validar o SMTP.
- As senhas estão **só** no `.env.foradafila` do servidor (`MAIL_FIQUE_PASSWORD`, `MAIL_NOREPLY_PASSWORD`, entre
  aspas simples porque começam com `$`). Nunca no Git, no código, no CLAUDE.md ou em respostas.
- Todo envio passa pela tabela `mensagem_envio` (corpo renderizado, status `enviado`/`erro`, motivo do erro) e
  sai depois da resposta HTTP. Links com token (convite, senha) não ficam gravados no corpo.
- Textos em `App\Support\Notificacoes`; layout em `resources/views/emails/layout.blade.php`; modelos editáveis
  do banco (`modelo_mensagem`) via `App\Support\ModeloMensagem` (evento → organizador → padrão).
- Enviados hoje: boas-vindas ao produtor, convite da equipe, ingressos confirmados, recuperação de senha
  (fique@) e aviso de cancelamento de inscrição (noreply@).
- Recuperação de senha sem tabela: URL assinada (60 min, só o caminho) com HMAC da senha atual (`SenhaController`).
- Localmente, sem senha no `.env`, os e-mails vão para `storage/logs/laravel.log`.

## Publicação (Hostinger)

- Autorizado pelo Bruno (07/11/2026): **toda imagem e todo recurso novo do site pode ser publicado direto** em
  foradafila.com.br, sem pedir confirmação a cada vez.
- **Como publicar:** commit e push na `main` mexendo em `plataforma/`. A Action `.github/workflows/build-plataforma.yml`
  instala as dependências (sem dev) e grava o resultado na branch `publicacao` (commit normal, sem force push).
  A Hostinger acompanha `publicacao` (auto-deploy do GitHub) e atualiza o `public_html`.
- **Nunca** apontar a publicação automática para a `main`: levaria `banco/`, `docs/` e `CLAUDE.md` para o site público.
  Isso já aconteceu por alguns instantes em 07/11/2026 durante a configuração.
- Não editar a branch `publicacao` à mão.
- O Laravel inteiro fica em `public_html`; o `.htaccess` da raiz manda tudo para `public/` e bloqueia arquivos ocultos.
- **`.env` de produção:** `/home/u839930205/domains/foradafila.com.br/.env.foradafila` (fora do `public_html`), lido por
  `bootstrap/app.php`. Para trocar: `hosting_files_generate-upload-url` e envio TUS para a **raiz** do servidor de
  arquivos (`.../api/tus/.env.foradafila`, sem o `public_html` no caminho).
- Sem SSH: não há `php artisan` no servidor. Mudança de banco = novo script `banco/V0xx` importado no phpMyAdmin.
- O ambiente da nuvem precisa ter liberados `srv1193-files.hstgr.io`, `foradafila.com.br`, `www.foradafila.com.br`.
- DNS no Registro.br com nameservers da Hostinger. SSL ativo (vitalício, Hostinger) com redirecionamento HTTPS.
- A conta Hostinger `u839930205` também hospeda outros sites (ex.: cartorio8oficiobh.com.br): não mexer neles.

## Decisões tomadas

- **Backend: Laravel 12 (PHP 8.3)** — confirmado pelo Bruno em 07/11/2026. Telas em Blade com padrão visual único
  (menu lateral), sem Filament por enquanto.
- Projeto piloto: **Estação Slod** (CNPJ 41.902.878/0001-08), cadastrada pelo Bruno em 07/11/2026. A conta nasce `ativo`
  (sem confirmação de e-mail enquanto não houver e-mail transacional).
- App de check-in como **PWA** (QR Code pela câmera, funciona offline), consumindo a mesma API.
- Rotinas automáticas (expirar reservas, virar lote, fila de e-mail, conciliação com o Mercado Pago) pelo
  agendador do Laravel, chamado por um cron a cada minuto no hPanel.
- Os models do Laravel devem mapear as tabelas que **já existem** (não recriar via migrations do Laravel);
  escopo global por `organizador_id`.

## Pendências

- [ ] Configurar o cron do agendador no hPanel (expirar reservas, virar lotes) quando houver vendas
- [ ] Área administrativa da plataforma (equipe BH Software) — futuro próximo
- [ ] **Preços:** tabela completa das faixas (1% no primeiro evento, progressivo a cada R$ 1.000): percentuais de
  cada faixa, base de cálculo, o que é "primeiro evento", demais eventos, gratuitos/cortesias. O schema atual (`plano`:
  percentual + fixo + mínimo por ingresso) **não representa faixas progressivas** — vai exigir V003. O plano
  "Lançamento" no banco está com 0%.
- [ ] Decidir o modo de cobrança (split ou fatura)
- [ ] Dados oficiais da BH Software (razão social, CNPJ) para Solução/rodapé/termos — hoje só "plataforma de tecnologia da BH Software"
- [ ] Canal de suporte da plataforma (e-mail/WhatsApp) — o FAQ hoje direciona ao organizador
- [ ] Validação jurídica de qualquer menção a responsabilidade civil e criminal do organizador (não publicada)
- [ ] Confirmar se o comprador pode cancelar sozinho a inscrição gratuita (ligado no piloto)
- [ ] Token de integração (API) — campo hoje bloqueado
- [ ] Validação jurídica do texto da declaração de cancelamento e do modelo de política de cancelamento
- [ ] Trocar as senhas das caixas fique@ e noreply@ (foram enviadas no chat) e usar senhas diferentes entre elas
- [ ] Confirmação de e-mail no cadastro e acesso por link para o comprador (o envio já existe)
- [ ] Lembrete do evento (`lembrete_evento`) depende do cron do agendador
- [ ] Cancelamento/reembolso de pedido pago: tabela `reembolso` exige `valor > 0`; entra com a integração de pagamento
- [ ] Confirmar com o Mercado Pago se dá para reter a comissão na venda (split / marketplace)
- [ ] Redigir com advogado os 4 termos (uso, responsabilidade do organizador, compra, privacidade)
- [ ] Validar a política de cancelamento, o direito de arrependimento e a cota de meia-entrada
- [ ] Criar um banco de homologação

## Próximos passos

1. Criar a aplicação no Mercado Pago (`MP_*` no `.env`, ver `docs/integracao-pagamentos.md`).
   Financeiro: conectar e homologar a conta do Mercado Pago (OAuth), checkout pago e pedido de reembolso.
2. Check-in (PWA) lendo o QR `FF1.<codigo>.<hmac>` e participantes no painel.
3. Confirmação de e-mail no cadastro e lembretes do evento.
