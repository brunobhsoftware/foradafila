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
| `banco/V008__administracao_taxas_impersonacao.sql` | Motor de taxas (`taxa_regra` com faixas por volume, `taxa_organizador` com isenções/validade), `impersonacao_log` e `auditoria.admin_impersonando_id`/`impersonacao_id`. Idempotente. Importado em produção em 07/11/2026 ("V008 ok") |
| `banco/V009__cupom_divulgacao.sql` | `cupom.tipo_desconto` ganha `divulgacao` (valor 0; ajusta o CHECK `ck_cupom_valor`). Idempotente. Importado em produção em 08/11/2026 ("V009 ok") |
| `banco/V010__taxa_servico_comprador.sql` | Quem paga a taxa: `organizador.taxa_comprador` (padrão), `evento.taxa_comprador` (NULL = padrão), `pedido.valor_taxa_servico`, `pedido_item.taxa_unitaria`. Idempotente. Aplicado em produção em 08/11/2026 ("V010 ok") |
| `banco/V011__cadastro_unico_pessoa.sql` | Cadastro único de pessoas (`pessoa`, chave CPF: nome, e-mail, telefone, origem). Idempotente. Aplicado em produção em 08/11/2026 ("V011 ok") |
| `banco/V012__recebimento_pela_plataforma.sql` | Recebimento pela plataforma: `organizador.pix_tipo/pix_chave/recebimento_preferido`, `conta_pagamento.modo` (direta/plataforma), tabela `repasse`, `pagamento.repasse_id`; `organizador.recebimento_modo` e colunas da aplicação própria em `conta_pagamento` (client_id, segredos e token de teste criptografados). Idempotente. Aplicado em produção em 08/11/2026 ("V012 ok"; reaplicado com o modo `propria`). Sem ele completo, o bloco da aplicação própria fica oculto (`Esquema::temPropria()`) |
| `banco/V013__checkout_pago.sql` | Checkout pago: `pedido.dados_participantes` (JSON até o pagamento ser aprovado) e `ck_pedido_valores` passa a somar `valor_taxa_servico`. Idempotente. Aplicado em produção em 08/11/2026 ("V013 ok") |
| `banco/V014__lote_exibir.sql` | `lote.exibir` — **flag calculada** (1 = na página agora, 0 = oculto), atualizada pela regra de venda; não editável. Idempotente. Aplicado em produção em 08/11/2026 ("V014 ok") |
| `banco/V015__formularios_inscricao.sql` | Formulários de inscrição: `formulario.padrao` e `evento.formulario_id`. Idempotente. Aplicado em produção em 08/11/2026 ("V015 ok") |
| `banco/V016__cpf_teste_e_nascimento.sql` | Limpa o CPF de teste do MP (12345678909) de `comprador`/`pessoa` e cria `pessoa.data_nascimento`. Idempotente. Aplicado em produção em 09/11/2026 ("V016 ok") |
| `banco/V017__marketing_smtp_listas.sql` | Opções de marketing: `smtp_conta` (até 5 SMTP da empresa, senha criptografada), `lista_email`/`lista_email_contato` e `email_descadastro`. Idempotente. Aplicado em produção em 09/11/2026 ("V017 ok"; sem ele, menu e telas ficam ocultos: `Esquema::temV017()`) |
| `banco/V018__convite_email.sql` | Convite por e-mail: `campanha` ganha uuid/lista_id/smtp_conta_id/enviados/erros/iniciada_em/concluida_em; tabela `campanha_envio` (fila por e-mail); `lista_email_contato.consentimento_em/_por`. Idempotente. **Aguardando importação em produção** (`Esquema::temV018()`) |
| `docs/apps-checkin.md` | **Apps de check-in**: instalar sem loja (PWA) e publicar no Google Play e na App Store (projeto `apps/checkin/`, Capacitor 6) |
| `apps/checkin/` | Projeto Capacitor dos apps Android/iOS, **com os projetos nativos gerados** (`android/`, `ios/`: ícones, tela de abertura, nome "FF Check-in", permissão de câmera); abre `https://foradafila.com.br/app`. Compilado pelo GitHub em `.github/workflows/apps-checkin.yml` (APK de teste, AAB assinado com os segredos `ANDROID_*`, build iOS de simulador) |
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
- **Quem paga a taxa é escolha do produtor** (decisão do Bruno, 08/11/2026; antes era "sem taxa para o comprador"):
  **embutida** (o comprador paga o preço; a taxa sai do valor do produtor) ou **repassada ao comprador** (preço + taxa; o produtor
  recebe o valor cheio). Padrão em Organização → Taxa da plataforma; cada evento pode mudar. Taxa por ingresso pago:
  `max(valor × % + fixo, mínimo)` vinda do motor da Administração (ex.: 5% com mínimo de R$ 1). Gratuito não tem taxa.
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
  **Sessões em `FF_ARMAZENAMENTO/sessoes`** (fora do public_html, 08/11/2026): a publicação apagava `storage/framework/sessions` e dava 419
  no meio da compra. Um 419 que ainda aconteça volta ao formulário com os dados e o aviso "sessão expirou" (`bootstrap/app.php`).
- Auth no model `Usuario` (tabela `usuario`, coluna `senha_hash`). Login bloqueia 15 min após 5 erros.
- Multi-organizador: `App\Support\Tenant` + trait `PertenceAoOrganizador` (escopo global, **falha fechada**: sem
  organizador definido a consulta não retorna nada). O middleware `organizador` roda antes do route model binding.
- Permissões `modulo.acao` vêm de `perfil_permissao`; use `@can('evento.publicar')` / `Gate::authorize(...)`.
  Vínculo com `todos_eventos = 0` limita aos eventos de `organizador_usuario_evento` (`Evento::visiveis()`).
- Recusas dos triggers (SQLSTATE 45000) viram erro de formulário via `App\Support\RegraDoBanco::executar()`.
- Toda ação relevante grava em `auditoria` via `App\Support\Auditoria::registrar()`.
- Datas: banco em UTC; tela em `America/Sao_Paulo` (helpers `data_local`, `para_input_data`, `utc_de_local`).
- **Meus eventos** (decisão do Bruno, 08/11/2026): cada evento num painel (`.evento-painel`): nome, local, início–fim, status, Abrir/Editar;
  botões Abrir, **Ingressos** (vai direto à seção `#ingressos` da página do evento, também com o evento publicado) e Editar;
  números — ingressos vendidos/ofertados, valor vendido (Σ `pedido.valor_total` pago/reembolso parcial/reembolsado), descontos,
  taxa da plataforma (só dos ingressos válidos/utilizados), devolvido (`reembolso` aprovado/concluído) e **valor líquido**
  (vendido − taxa − devoluções); link público com Copiar, Compartilhar (`data-compartilhar`: `navigator.share` ou copia) e WhatsApp.
  Sem a coluna Capacidade.
- **Ocultar evento publicado** (decisão do Bruno, 09/11/2026): interruptor **"Evento visível" / "Evento oculto"** (`painel/eventos/_visibilidade`) ao lado de "Editar ingressos" no editar e na página do evento (publicado ou pausado →
  `rascunho`, permissão `evento.publicar`, `Evento::TRANSICOES`): some do site (404, vitrine e página da organização) e para de vender; ingressos já
  emitidos continuam valendo; "Publicar evento" mostra de novo (o gatilho refaz as conferências de publicação).
- Telas prontas (produtor): cadastro, login, convite da equipe, troca de organização, painel, **Meus eventos**
  (lista, novo, editar, publicar/pausar/encerrar/cancelar, link da página pública), tipos de ingresso e lotes,
  equipe, níveis de acesso, dados da organização, Pedidos, Participantes, Check-in, Cupons, Convidados, Mensagens e Financeiro.
- Site público (`layouts/publico` + `site/_topo`, `site/_rodape`, CSS `public/assets/css/publico.css`):
  início, `/solucao`, `/precos`, `/ajuda`, `/eventos` (vitrine), `/e/{slug}` (evento), `/p/{slug}` (organizador),
  `/termos/{tipo}`. **Menu do celular** (botão de 3 linhas, 09/11/2026): abria e fechava no mesmo clique por causa do fechamento do menu lateral do painel em `app.js` (corrigido); no celular mostra também Painel do produtor, App de check-in, Meus dados e Sair (`.so-celular-pub`). **Link do evento com código fixo** (decisão do Bruno, 08/11/2026): `Evento::linkPublico()` = `/e/{10 primeiros
  caracteres do uuid}`, que nunca muda; **só o código abre** (`EventoPublicoController::buscar()`; links pelo nome/slug dão 404, decisão
  do Bruno, 08/11/2026). Todo link e formulário (painel, cupons, vitrine, compra `inscricao.create/store`) usa `codigoPublico()`.
  Nos testes, `codEv(slug)` busca o código no banco. Menu superior muda conforme quem está logado (produtor e/ou comprador).
- Comprador: guard **`comprador`** (tabela `comprador`), separado do produtor. Rotas em `/minha-conta`:
  entrar, criar conta, Meus ingressos, segunda via (`/ingressos/{uuid}`, QR no navegador com
  `public/js/vendor/qrcode-generator.js`, MIT) e cancelamento de inscrição gratuita.
- **Inscrição gratuita real** (`InscricaoController`): só lotes com preço 0. Baixa de estoque por UPDATE condicional,
  numeração por `evento.ultimo_numero_ingresso`, `codigo_qr` 256 bits + HMAC (`Ingresso::conteudoQr()`),
  participante por ingresso, aceite de `compra_comprador` e `privacidade`, chave de idempotência.
  Lotes pagos: vendidos pelo **checkout pago** (abaixo) quando a organização tem conta de recebimento ativa; senão "venda online em breve".
- **Compra sem senha, CPF primeiro** (decisão do Bruno, 08/11/2026): em "1. Seus dados" o comprador sem login digita o CPF
  (`GET /compra/cpf/{cpf}`, throttle `compra-cpf` 10/min, `InscricaoController::cpf`) ou clica em **"Não desejo informar o CPF"**
  (`sem_cpf`). Os ingressos, cupom, pagamento e termos (`data-depois-cpf`) só aparecem depois de um dos dois. CPF conhecido (comprador ou
  `pessoa`) → nome e e-mail/celular **mascarados**, usa o cadastro; novo ou sem CPF → nome e e-mail obrigatórios, celular opcional. Sem
  senha (`compradorDaCompra`: comprador do CPF, do e-mail ou novo com `senha_hash` NULL e CPF se houver). Só o comprador **novo** entra na
  conta. O **1º ingresso já vem com os dados do comprador** (`participantes[0][usar_comprador]` = 1, completado também no servidor);
  botão **"Estou comprando para outra pessoa"** limpa e desliga. Compra paga: **"Forma de pagamento"** em cartões Pix/Cartão
  (`forma_pagamento` → `config.payment_method` da order: Pix só Pix com `default_id`, cartão sem Pix/boleto); **cartão sem CPF
  abre o pré-cadastro** `publico/inscricao-dados` (CPF do titular, celular opcional, nome e e-mail já preenchidos; a compra segue em campos
  ocultos via `achatar()`, `etapa_dados`), e o CPF vai para o comprador e o cadastro único. `items[].external_code` = 30 primeiros
  caracteres do uuid do lote sem hífens (o MP aceita até 30; com 36 dava 500 em produção, 08/11/2026). O CPF enviado ao MP é o de quem paga.
  Termos: um aceite só (`aceite_termos`). Testes usam `[name=cpf]` + `[name=aceite_termos]`. `publico.css` ganhou `.opcao-cartao`,
  `.botao--contorno` e `.botao--pequeno` (faltavam: o botão aparecia transparente).
- **"Editar meus dados" na compra** (decisão do Bruno, 09/11/2026; `CadastroCompradorController`, `/compra/cadastro`): com o CPF conhecido,
  o botão leva a "Meu cadastro" (CPF, nome, e-mail, celular e senha opcional). **Só edita depois do código de 6 dígitos** enviado ao e-mail
  do cadastro (`CodigoVerificacao`, finalidade `cadastro-comprador`; throttles `compra-cadastro*`); ao salvar entra na conta e volta à compra
  (`volta`, só caminho interno). CPF só no cadastro único com e-mail → cria o comprador.
- **Pix direto na nossa página** (decisão do Bruno, 09/11/2026: o Checkout Pro pedia login/CPF/senha no Mercado Pago): com `forma_pagamento=pix`
  a order vai com `processing_mode: automatic` e `transactions.payments[{payment_method: {id: pix, type: bank_transfer}, expiration_time: PT30M}]`
  (sem `config.online`); o "copia e cola" (`qr_code`) fica em `pagamento.resposta_gateway.pix` e `link_pagamento` aponta para
  `/pedido/{uuid}/pix` (`PagamentoController::pix`: QR desenhado com `qrcode-generator.js`, botão copiar, consulta `/pedido/{uuid}/situacao`
  a cada 5 s e vai ao resultado quando aprova).
- **Cartão na nossa página** (decisão do Bruno, 09/11/2026: nada de login no Mercado Pago/Mercado Livre): `forma_pagamento=cartao` cria o
  pedido reservado e vai para `/pedido/{uuid}/cartao` com o **Card Payment Brick** (`sdk.mercadopago.com/js/v2`, Public Key da conta:
  `Checkout::publicKey` → `conta_pagamento.public_key/teste_public_key` na aplicação própria ou `mp_public_key`/`mp_teste_public_key`
  da BH, campos novos nos blocos 2/3 da Administração → Mercado Pago). O Brick pede número, validade, CVV, titular, **CPF** e parcelas
  (até 12) e devolve o token; `POST /pedido/{uuid}/cartao` → `Checkout::pagarCartao` cria a order `processing_mode: automatic` com
  `transactions.payments[{payment_method: {id, type, token, installments}}]` e decide na hora: aprovado → `sincronizar`/`aprovar`;
  recusado → mensagem (`mensagemRecusa`) e o pedido segue reservado para outro cartão. O CPF do titular vai para o comprador e o cadastro
  único **só em produção, se o CPF for válido e o 1º nome do titular** (`MercadoPago::tokenCartao` → `cardholder.name`, `Checkout::mesmoPrimeiroNome`)
  **for igual ao do comprador** (09/11/2026: o CPF de teste 123.456.789-09 do titular APRO foi gravado no cadastro do Bruno). O pré-cadastro do cartão (`publico/inscricao-dados`) ficou desativado. **Sem a Public Key cadastrada o cartão mostra
  "indisponível, escolha Pix".**
- **Ingressos na hora, sem login, e PDF por e-mail** (decisão do Bruno, 09/11/2026): toda compra (gratuita, Pix, cartão) termina em
  `/pedido/{uuid}/ingressos` ("Seus ingressos": QR de cada ingresso, "Baixar ingressos em PDF" `/pedido/{uuid}/ingressos.pdf`, imprimir).
  Acesso (`PagamentoController::podeVer`): pedido feito neste navegador (sessão `pedidos_compra`, `PagamentoController::lembrar`), comprador
  dono logado ou **link assinado do e-mail** (`linkIngressos`, 90 dias, assinatura só do caminho). O e-mail de confirmação leva o botão
  "Ver meus ingressos" (esse link), o **PDF anexo** (`App\Support\IngressoPdf`: PDF escrito à mão, uma página A4 por ingresso, Helvetica
  WinAnsi, QR em retângulos por `App\Support\QrCode` — porte em PHP do qrcode-generator, módulo a módulo igual ao do navegador, conferido
  com jsQR) e, para quem não tem senha, "Crie a sua senha" (`SenhaController::link`, 72 h, removido do corpo gravado). Anexos do
  `Email` vão em base64 (o dispatch serializa em JSON). Sem dependência nova no composer (o download do GitHub é bloqueado na nuvem).
- **Entrada de "Meus ingressos" só com o e-mail** (decisão do Bruno, 09/11/2026; `AcessoCompradorController::identificar`, `POST /minha-conta/identificar`):
  tem senha → `/minha-conta/entrar/senha` (senha, "Receber um código no e-mail", "Esqueci a senha"; o POST continua em `CompradorAuthController::autenticar`);
  tem cadastro sem senha → código direto; **sem cadastro** → código (`cadastro-novo`, chave `crc32` do e-mail) → `/minha-conta/completar` ("Complete seu
  cadastro": nome, CPF, nascimento, celular e senha, todos obrigatórios) → cria o comprador já com `email_verificado_em` e entra.
  **Código só uma vez** (decisão do Bruno, 09/11/2026): depois de confirmar o e-mail (código, "Complete seu cadastro" ou link "Crie a sua senha" do e-mail —
  `SenhaController::salvar` agora entra direto para o comprador, com `comprador_verificado`), a pessoa já cai em Meus ingressos e edita os dados sem outro código.
- **Acesso do comprador por código** (decisão do Bruno, 09/11/2026; `AcessoCompradorController`): botão **"Acessar meus ingressos"** na tela
  dos ingressos (`POST /pedido/{uuid}/acessar`, só para o pedido desta sessão; logado e dono → direto) e **"Entrar com código"** em
  `/minha-conta/entrar` (`codigo.enviar`, resposta igual exista ou não a conta) mandam um código de 6 dígitos (`CodigoVerificacao`,
  finalidade `acesso-comprador`) → `/minha-conta/codigo`: com o código entra (lembrar sessão, marca `email_verificado_em`) e, sem senha,
  "Aproveite e cadastre a sua senha" (opcional). Área do comprador com abas (`comprador/_abas`): **Meus ingressos** (Próximos eventos /
  Eventos que você já foi) e **Meus dados** (`/minha-conta/dados`: nome, celular, **data de nascimento**, CPF validado se ainda não tem — campo em branco,
  nunca inventado —, criar/trocar senha; e-mail só leitura). **Editar só com o e-mail validado nesta sessão** (`AcessoCompradorController::verificado`,
  sessão `comprador_verificado`: entrou com e-mail+senha, com o código ou salvou "Meu cadastro" com código); quem entrou só pela compra vê os dados
  travados e "Enviar código para o meu e-mail" (`comprador.dados.codigo`, volta a Meus dados).
  **Meus dados limpo** (09/11/2026): só "Meus dados cadastrais" + nome, e-mail (só leitura), CPF, celular e data de nascimento — **todos obrigatórios**,
  sem textos de ajuda — e os botões Salvar cadastro, Alterar meu e-mail e Alterar senha (que também cria a primeira senha; aba "Criar senha" para quem não tem).
  **Senha separada dos dados** (decisão do Bruno, 09/11/2026): Meus dados só mostra "Criar uma senha" para quem ainda não tem; quem tem usa a aba/botão
  **Alterar senha** (`/minha-conta/senha`, `comprador.senha`, exige e-mail validado na sessão, avisa por e-mail). Salvar mostra "Seu cadastro foi atualizado".
  Abas numa faixa clara abaixo do cabeçalho escuro (`.pub-abas` em `publico.css`: pílulas, ativa laranja, Sair à direita).
  **Alterar meu e-mail** (`/minha-conta/email`, 09/11/2026): com o e-mail atual validado na sessão, código ao e-mail **novo** (`email-comprador`,
  sessão `email_novo`); só troca com o código; avisa o antigo e o novo. Meus dados com a legenda "Meus dados cadastrais". Meus ingressos: **imagem do
  evento** em cada pedido (`.pub-pedido__capa`, link para a página) e **"Enviar para meu e-mail"** (`comprador.reenviar`: confirmação com o PDF).
  **Cancelamento pelo comprador** (decisão do Bruno, 09/11/2026; `MeusIngressosController::regraCancelamento` / `cancelarIngresso`): em cada
  ingresso, "Enviar para meu e-mail" (`comprador.ingresso.enviar`) e "Cancelar ingresso" (abre `.pub-cancelar` com a regra): **até 7 dias da compra**
  (`pago_em`, `DIAS_ARREPENDIMENTO`) devolve tudo; **depois**, só o ingresso (a taxa repassada, `pedido_item.taxa_unitaria`, fica retida); só antes do
  início do evento. Usa `CancelamentoIngressos::cancelar(..., $valores, 'comprador')` (devolução pelo MP, `reembolso.solicitado_por = comprador`,
  auditoria `ingresso.cancelar_pelo_comprador`) e e-mail `Notificacoes::ingressoCanceladoPeloComprador`. **Corrigido:** o 2º cancelamento do mesmo
  pedido não devolvia pelo MP (só buscava pagamento `aprovado`; agora também `estorno_parcial`, e depois de uma parcial manda sempre o valor).
  Cabeçalho: "Olá, nome completo" e o e-mail embaixo.
- **Checkout pago** (`App\Support\Checkout`, 08/11/2026): a mesma tela de inscrição vira "Comprar ingresso" com o resumo (preço + taxa
  repassada) e "Ir para o pagamento". `InscricaoController::reservarPago` reserva o estoque (`lote.reservado`, UPDATE condicional) por
  `RESERVA_MIN` (30) min, cria o pedido `aguardando_pagamento` (participantes em `dados_participantes`; `pedido_item.taxa_unitaria` =
  taxa da plataforma por ingresso, sempre; `subtotal` = (preço − desconto) × qtd; a taxa repassada fica em `pedido.valor_taxa_servico`),
  cria a **order** (API de Orders, SDK) com o token da conta de recebimento (`Checkout::token`: aplicação da BH para `plataforma`,
  Access Token da empresa para `propria`) e redireciona ao `checkout_url`. Linha em `pagamento` (order id, comissão = Σ taxa_unitaria,
  líquido do produtor). Confirmação por **webhook** (`WebhookController::processar` → `Checkout::sincronizar`) e pelo **retorno**
  `/pedido/{uuid}/pagamento` (consulta a order na API, não confia na URL): `processed`/`accredited` → `aprovar()` (reserva vira venda,
  numera, emite ingressos e participantes, cadastro único, e-mails; idempotente com lock); recusado/cancelado/expirado → `liberar()`.
  Reserva vencida volta ao estoque sem cron (`liberarVencidas`, ao abrir o evento/comprar; confere antes no MP porque Pix pode ter sido
  pago). Pago depois de a reserva vencer e o lote esgotar → pedido cancelado + pagamento "devolver ao comprador". "Meus ingressos" mostra
  pedido aguardando com "Pagar ou conferir pagamento". O modo de teste das vendas é **só por empresa** (a chave geral "Vendas em modo de teste" da Administração foi
  removida em 08/11/2026 a pedido do Bruno: ela colocaria todas as empresas em teste). **Repasses** (`/admin/repasses`, `App\Support\Repasses`): por produtora, liberado (fim do
  evento + 3 dias úteis), aguardando e taxa; "Registrar repasse" (data, E2E/TED) grava `repasse` e fecha os pagamentos (`repasse_id`).
  **Homologação por empresa** (decisão do Bruno, 08/11/2026): Financeiro → "Ambiente das vendas" (cartões Produção/Homologação;
  `parametro_sistema` `mp_vendas_teste_org_<id>`; `Checkout::modoTeste($orgId)` = só da empresa): o checkout usa as credenciais de
  teste da conta de recebimento e a página do evento/compra mostra "Ambiente de teste". **Devolução pelo Mercado Pago**: cancelar
  ingresso pago (Participantes/Pedidos) chama `Checkout::reembolsar()` → `POST /v1/orders/{id}/refund` (total, ou parcial com
  `transactions[{id: gateway_pagamento_id, amount}]`) com o token do ambiente em que foi pago (`tokenDoAmbiente`); pagamento vira
  `estornado`/`estorno_parcial` e o reembolso fica `concluido`; se o MP recusar, nada é cancelado e aparece o erro.
  Financeiro da produtora mostra o **extrato** (vendas, taxa, liberado, aguardando, repasses recebidos). Ainda não:
  parcelamento configurável, cupom de desconto no checkout pago já aplica o desconto (`Cupom::desconto`). Teste `fluxo-checkout.js`
  (31, porta 8001 + MP falso).
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
  Lote pago só publica com conta de recebimento ativa. **Exibição na página = regra de venda** (decisão do Bruno, 08/11/2026; a opção "Exibir na página" do V014 foi retirada): a página do evento mostra só os lotes à venda agora (`loteAVenda`) — "Por data" dentro do período, "Por lote" depois que o anterior esgota/encerra/passa do fim; sem nenhum, mostra "As vendas abrem em ...". A flag `lote.exibir` guarda essa situação (`EventoPublicoController::sincronizarExibicao`, ao abrir a página pública e o evento no painel, sem cron); o painel mostra "Na página agora"/"Oculto da página agora", só leitura.
  **Taxa da plataforma** (V010, `App\Support\TaxaServico`): `repassada()` (evento → organização) e `paraPreco()` (taxa do
  `MotorTaxas`, comprador paga, produtor recebe). Organização tem a seção "Taxa da plataforma" (embutida/repassada, com exemplo
  de R$ 50); o formulário do evento tem "Taxa da plataforma nos ingressos pagos" (padrão da organização/embutida/repassada), com exemplo de R$ 50 que muda na hora conforme a opção (`data-exemplo-taxa`; sem taxa configurada, ilustrativo de 5%). A taxa não é gravada no lote: é calculada na hora, então mudar o modelo atualiza todos os ingressos do evento.
  A página do evento mostra o preço com a taxa separada quando repassada ("R$ 5,00 + R$ 1,00 de taxa"); o painel de lotes mostra
  "comprador paga" ou "você recebe". Textos do site (início, Preços, Ajuda, telas de acesso) dizem que o produtor escolhe.
  Os campos `pedido.valor_taxa_servico`/`pedido_item.taxa_unitaria` serão preenchidos pela venda paga (checkout). Sem o V010,
  nada disso aparece (`Esquema::temV010()`). A vitrine ("A partir de") ainda mostra o preço sem a taxa.
  **Fim das vendas** (decisão do Bruno, 08/11/2026): vai até o **fim do evento** (`data_fim`); o lote novo já sugere esse horário.
  Ao editar a data do evento, os lotes que terminavam junto com o evento (no início ou no fim antigos) ou que passariam do novo
  término são ajustados para o novo `data_fim` (`EventoController::ajustarFimDasVendas`, auditoria `lote.fim_vendas_ajustado`).
  **Nunca há venda depois do fim do evento:** salvar o evento sempre corrige lotes com `venda_fim` > `data_fim` (mesmo sem mudar a data) e a página pública não vende após o fim. Dashboard → Próximos eventos tem "Abrir" e "Editar".
  A edição do tipo e do lote abre embaixo, em largura total (botões com `data-alternar`).
- **Recebimento pela plataforma** (decisão do Bruno, 08/11/2026, enquanto o Mercado Pago não libera o OAuth da aplicação —
  "O aplicativo não está pronto para se conectar"): as vendas pagas caem na conta do Mercado Pago da **BH Software**, que retém a
  taxa e **repassa ao produtor em até 3 dias úteis após o fim do evento** (`Recebimento::PRAZO_DIAS_UTEIS`) por Pix ou conta.
  Financeiro mostra "Como você recebe" (3 passos + exemplo de 10 × R$ 100 embutida/repassada) e "Dados para o repasse" (preferência
  Pix/conta; chave Pix por tipo com validação; conta bancária com os 4 campos) — os dados bancários **saíram de Organização**.
  Salvar cria/ativa `conta_pagamento` `modo = plataforma` (`App\Support\Recebimento::sincronizar`), que atende o gatilho de publicação;
  publicar evento liga `evento.conta_pagamento_id` a ela. "Conectar Mercado Pago" fica **em breve/desativado** (`MP_CONEXAO_DIRETA`,
  padrão false; o código do OAuth continua). Teste `fluxo-recebimento.js` (15).
  **Aplicação Mercado Pago própria da empresa** (decisão do Bruno, 08/11/2026): no Financeiro, "Mercado Pago da empresa (aplicação
  própria)" com o bloco **1. Aplicação** (só Client ID e Client Secret — um par por aplicação, decisão do Bruno 08/11/2026), **2. TESTE** e **3. PRODUÇÃO** (Access Token, Public Key e assinatura dos webhooks de cada modo) e **4. Pagamento de teste** único com a escolha Teste/Produção em cartões de opção (segredos
  criptografados, em branco mantém), endereço de webhook **por empresa** (`/webhooks/mercadopago/{uuid da conta}`, confere com a
  assinatura dela e grava `webhook_evento.conta_pagamento_id`) a conta própria é criada ao abrir o Financeiro (mostra logo a URL do webhook, o site e o tipo de solução para copiar); **pagamento de teste** em cada bloco (homologação com o token de teste e produção com o de produção; CPF primeiro, nome completo, e-mail, telefone, valor; `financeiro.mp.propria.teste`, link + QR em `painel/financeiro/teste-link`, retorno público `admin.mercadopago.teste.retorno` que usa o token da empresa quando o cache `mp_teste_<ref>` traz `conta`) e "Verificar de quem é o token" (`/users/me`; o de produção válido
  ativa a conta `modo = propria`, preenchendo `homologada_em` por causa do gatilho `trg_conta_bu`). "Onde as vendas são recebidas":
  **pela plataforma, com repasse** (padrão) ou **direto na conta da empresa** (`organizador.recebimento_modo`; só com a aplicação
  ativa; troca a conta dos eventos não encerrados; nesse modo a taxa da plataforma é cobrada em fatura). `Recebimento::contaAtual()`
  escolhe a conta na publicação e no checkout. Teste `fluxo-mp-propria.js` (18, porta 8001 + MP falso na 8099). **Próximo:** checkout pago com o token da BH,
  webhook confirmando pedido e emitindo ingressos, saldo e repasses na Administração.
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
  integração; pagar com a conta de teste compradora) ou **Produção** (token client_credentials). A página tem o bloco **"1. Aplicação"** (só Client ID e Client Secret),
  **"2. Ambiente de TESTE"** / **"3. Ambiente de PRODUÇÃO"** (Access Token e assinatura dos webhooks de cada modo) e **"4. Pagamento de teste"** único (cartões Teste/Produção) (os campos `teste_client_*`/`teste_webhook_secret` continuam aceitos no código, sem tela), salvar, "Verificar de quem é o token" e o botão de pagamento (teste / de verdade). Access Token de
  produção (`mp_access_token`, opcional) tem prioridade sobre o client_credentials. **SDK oficial `mercadopago/dx-php` (^3.0, no composer)** cria e consulta as orders (`MercadoPago::sdk()` ajusta token e `BASE_URL`; recomendação "SDK do backend" da Qualidade da integração). **O pagamento de teste usa a API de Orders**
  (`POST /v1/orders`, a aplicação é "Checkout Pro com Orders"; `MercadoPago::criarOrder()`/`order()`), com o que a Qualidade da
  integração pede: payer (e-mail, nome, sobrenome, CPF, telefone), items (title, description, category_id `tickets`, external_code,
  quantity, unit_price), `config.statement_descriptor` FORADAFILA, `config.online.success_url/failure_url/pending_url`, 3DS `config.online.transaction_security` (`validation: on_fraud_risk`, `liability_shift: required`; recomendação da Qualidade, 08/11/2026) e
  `additional_info` (`payer.authentication_type`, `registration_date`, `is_first_purchase_online`, `last_purchase`). O formulário
  pede os dados do comprador. Retorno por `?ref=` (cache `mp_teste_<ref>` → order + ambiente). Webhook: mesma URL; aceita a
  assinatura de produção ou a de teste (`mp_teste_webhook_secret`, opcional) — a lista mostra "Válida (teste)". O botão do pagamento
  de teste não redireciona: mostra o link (copiar + QR Code) para abrir em janela anônima/celular, sem a conta da BH Software logada
  (o MP recusa misturar conta real e de teste). Botão "Verificar de quem são os tokens" (`/users/me` com cada token: conta, apelido,
  conta de teste ou real; avisa se os dois são da mesma conta). O retorno `/admin/mercadopago/teste/retorno` é aberto (sem login só mostra os IDs). Empresas e taxas/isenções: em breve. Administrador sem empresa entra direto em `/admin`.
  **Empresas** (`/admin/empresas`, V008): lista com busca (nome/CNPJ), filtros (situação, período), ações "Assumir conta",
  "Taxas e isenções" e "Status"; detalhe (`/admin/empresas/{uuid}`) com números, usuários, integrações, situação (ativo/em teste/
  suspenso/encerrado + motivo), taxas específicas, acessos da Administração e auditoria. **Assumir conta** (`App\Support\Impersonacao`):
  sessão `impersonacao` (admin, empresa, log); o middleware `organizador` monta um vínculo virtual com o perfil Produtor; faixa
  laranja no painel com "Sair do modo assumir conta" (`POST /admin/sair-da-conta`); toda auditoria nesse período leva
  `admin_impersonando_id` e `impersonacao_id`, e `impersonacao_log.acoes` conta as ações. **Taxas** (`/admin/taxas`,
  `App\Support\MotorTaxas::calcular()`): regras padrão por operação com faixas de volume mensal (sem sobreposição), % + fixo,
  mínimo, carência para conta nova; taxa específica da empresa (isenção ou valores próprios; vazio = padrão) com início/fim;
  encerrar = fim ontem (ou remove se ainda não valeu); **Editar** a taxa existente abre embaixo da linha (`admin.empresas.taxas.editar`,
  auditoria `plataforma.taxa_empresa_editar`) — fim em branco = sempre ativa; "Nova taxa" fica recolhido quando já existe uma. Sem o V008, essas telas mostram aviso (`admin.precisa-v008`).
  Ainda não há cobrança aplicada: o motor só calcula (simulação na tela da empresa).
  **E-mails enviados** (`/admin/emails`): últimos 100 de `mensagem_envio` (para, assunto, situação, erro; sem o corpo), busca e filtro; ao buscar, mostra "Quem tem esse e-mail" (login de produtor ativo/desativado/sem senha ou só e-mail de contato da organização) e o botão "Enviar link de nova senha" (também no detalhe da empresa, `admin.empresas.senha`, auditoria `admin.senha_link_enviado`).
  **Convite pendente + "Esqueci a senha"**: ao salvar a nova senha, convites `convidado` não vencidos viram `ativo` (auditoria `equipe.aceitar_convite` via nova_senha); antes o login caía em "acesso não está ativo". Teste `fluxo-senha-convite.js`.
  **Primeiro acesso:** qualquer conta sem senha (administrador, pessoa da equipe convidada, comprador de cortesia) usa "Esqueci a senha" (link de 60 min, `SenhaController::podeRedefinir`).
  Modelo decidido pelo Bruno (07/11/2026): **aplicação única da BH Software + "Conectar" de cada produtor**; o dinheiro
  cai direto na conta do produtor. O Financeiro do produtor mostra a etapa 1 só como "Liberação pela plataforma".
- **Vendas → Participantes** (`/painel/participantes`, `ParticipantesController`): por evento (seletor; padrão = próximo), busca
  (nome, e-mail, nº do ingresso, código do pedido), filtros (situação, lote), resumo (válidos, check-in, emitidos, cancelados),
  botão "Ações" abre uma linha com **reenviar ingresso** (para o participante ou o comprador, via `Notificacoes::ingressosConfirmados`),
  **Imprimir ingresso (PDF)** (`ingressos.pdf`, `IngressoPdf` só desse ingresso), **Copiar link do ingresso** (`/ingresso/{uuid}` assinado, 90 dias,
  abre só esse ingresso sem login, `PagamentoController::linkIngresso`) e o botão **Cancelar este ingresso**, que só então mostra o formulário de
  cancelamento (decisão do Bruno, 09/11/2026) — (mostra o valor pago a devolver; motivo, observação, "já devolvi", avisar por e-mail). Exportar CSV (`;`, BOM;
  CPF mascarado sem `participante.ver_dados_completos`). **Vendas → Pedidos** (`/painel/pedidos`, `PedidosController`): lista com
  filtros e totais, detalhe com ingressos e devoluções, reenviar confirmação, cancelar o pedido. Cancelamento compartilhado em
  `App\Support\CancelamentoIngressos` (devolve estoque; ingresso pago → `reembolsado` + linha em `reembolso`/`reembolso_ingresso`
  com status `aprovado` = a devolver ou `concluido`; pedido → `reembolso_parcial`/`reembolsado`/`cancelado`). Aviso ao comprador:
  `Notificacoes::ingressosCanceladosPelaOrganizacao` (fique@). Permissões: `participante.ver`, `participante.exportar`, `pedido.ver`,
  `pedido.reembolsar`. A devolução do dinheiro em si é do produtor (com o Mercado Pago, sairá pela API).
- **Vendas → Check-in** (`/painel/checkin`, `CheckinController`): por evento; campo para leitor USB/Bluetooth ou digitação (QR
  `FF1.<codigo>.<hmac>` com assinatura conferida antes do banco, ou número `FF-0001-000123`/só o número), câmera pelo
  `BarcodeDetector` do navegador quando existe, senão **jsQR** (iPhone, Safari, computador; 09/11/2026 — antes aparecia "Este navegador não lê QR"), busca manual (nome, CPF, número) com "Registrar entrada",
  contadores (entraram, válidos, %, dentro agora se houver reentrada), últimas 15 leituras e "Desfazer" (`checkin.configurar`).
  Resultados: aceito, já utilizado, cancelado, outro evento, inexistente, assinatura inválida, portões fechados (antes de
  `abertura_portoes` — o cadastro preenche com o início do evento). Entrada por UPDATE condicional (dois portões não passam o mesmo
  QR); toda leitura em `checkin_log` (método, resultado, operador). Saída/reentrada só com `permite_reentrada`.
  Ainda não: PWA offline e cadastro de portões/dispositivos.
- **App de check-in da equipe** (decisão do Bruno, 09/11/2026; `/app`, `App\Http\Controllers\App\CheckinAppController`, layout `layouts/app-checkin`,
  CSS `public/css/checkin.css`, inspirado no app do Sympla): login da equipe (`auth` + `organizador`; volta ao `/app` depois do login); lista de eventos
  (Hoje — check-in liberado / Próximos / Últimos 30 dias, com imagem e data); evento com abas **Vendas** (`pedido.ver`: total, ingressos, vendido hoje,
  por ingresso) / **Check-in** (medidores realizados/restantes, atualiza a cada 15 s por `app.numeros`, botão Participantes) / **Ferramentas** (participantes,
  adicionar pedido, divulgar, códigos promocionais, painel — conforme as permissões); botão flutuante → **leitor** `/app/evento/{uuid}/ler` (câmera traseira,
  `BarcodeDetector` quando existe, senão **jsQR** `public/js/vendor/jsQR.js`, Apache-2.0 — funciona no iPhone; tela inteira verde/laranja/vermelha com o nome,
  vibra e apita; digitar o número) e **participantes** (busca, filtros Todos/Faltam/Entraram, botão Check-in). Registra pelo mesmo
  `CheckinController::registrar`. PWA: `public/app.webmanifest`, `public/app-sw.js` (cache só de CSS/JS/ícones), ícones `public/app-icons/`.
  Painel → Check-in mostra o QR/link do app; no celular o menu lateral tem **App** logo abaixo de Meus eventos (`.so-celular`, some acima de 900 px) e fica compacto, com altura `100dvh` e rolagem própria (Equipe, Níveis de acesso e Organização ficavam escondidos embaixo). **Regra do dia do evento** (`App\Support\JanelaCheckin`): check-in só de `abertura_portoes` (nunca antes da
  meia-noite do dia do início; o cadastro recusa abertura em outro dia) até `data_fim`; fora disso `fora_do_horario` com "O check-in abre em ..." / "O evento
  terminou em ...". **Aba Vendas completa** (decisão do Bruno, 09/11/2026; `CheckinAppController::vendas`): total vendido, líquido a receber, gráfico
  de rosca por ingresso (`conic-gradient`), barra vendidos/ofertados, cartões (vendidos + cortesias, vendidos hoje + valor, pedidos + ticket médio, taxa,
  devolvido, líquido), por ingresso, "Ingressos para venda" (situação de cada lote) e botão **Vender ingressos** (página pública). Testes `fluxo-app.js` (20, iPhone 13) e `fluxo-checkin.js` (18, o evento passa a estar acontecendo agora).
- **Divulgação → Cupons** (`/painel/cupons`, `CuponsController`, model `Cupom`): código (maiúsculas, único por organização),
  percentual (≤ 100%) ou valor fixo, evento ou todos os eventos, lotes específicos (`cupom_lote`; nenhum = todos), limite de usos
  e por CPF, pedido mínimo, validade; situação (ativo, agendado, esgotado, expirado, desativado); editar (limite não fica abaixo
  dos usos), ativar/desativar, filtro por evento. `Cupom::desconto($valor, $loteId, $subtotal)` calcula o desconto — **ainda não
  é usado no checkout** (entra com a venda paga, gravando em `cupom_uso`). **Cupom de divulgação** (V009, decisão do Bruno
  08/11/2026): tipo "Divulgação" sem desconto, limites, pedido mínimo nem validade — só conta quantos ingressos cada pessoa trouxe.
  Botão "Copiar link" (`/e/{slug}?cupom=CODIGO` ou `/p/{slug}?cupom=` se vale para todos os eventos); o código vai para a sessão
  (`cupom_divulgacao`) e a inscrição gratuita traz o campo "Código de divulgação ou cupom" preenchido; o pedido grava
  `pedido.cupom_id` + `cupom_uso` (desconto 0) e soma `cupom.usos`. A lista mostra a coluna **Ingressos** (válidos que saíram
  por cada código; cancelados saem da conta). Sem o V009 a opção não aparece (`Esquema::temV009()`).
- **Divulgação → Convidados** (`/painel/convidados`, `ConvidadosController`): listas por evento (`lista_convidados`: nome, lote
  usado para emitir, limite de convidados, máximo de acompanhantes); convidados (`convidado`) um a um ou colados em lote
  ("Nome; e-mail; acompanhantes", com erros por linha); "Emitir e enviar" (um ou todos os pendentes com e-mail; exige
  `pedido.emitir_manual`) cria pedido `origem = lista_convidados` com titular + acompanhantes via `App\Support\EmissaoCortesia`
  (qualquer lote do evento, baixa estoque, numera) e manda o ingresso por e-mail; "Remover" cancela os ingressos já emitidos.
  O comprador do convidado é criado **sem senha** e define a senha por "Esqueci a senha" (`SenhaController::podeRedefinir` aceita
  comprador sem senha). Ainda não: link de confirmação do convite (RSVP) e links de acesso privado.
- **Divulgação → Mensagens** (`/painel/mensagens`, `MensagensController`): e-mails automáticos editáveis (`modelo_mensagem`:
  confirmação da compra — já usada — e lembrete do evento — depende do cron), para a organização ou só para um evento (o evento
  herda da organização, que herda do padrão), botões de variáveis, pré-visualização com dados de exemplo, "restaurar padrão"
  (desativa o texto próprio: `ativo = 0`, porque `mensagem_envio.modelo_id` aponta para ele); comunicado avulso para quem tem
  ingresso válido no evento (compradores ou participantes com e-mail; até 300 por envio, throttle `comunicado` 5/hora); histórico
  dos últimos 40 envios. Os módulos do menu do produtor não mostram mais "em breve" (só a rota genérica continua existindo).
- **Opções de marketing** (V017, decisão do Bruno 09/11/2026): Organização → "Configurar e-mails de divulgação" (`/painel/organizacao/marketing`,
  `MarketingController`, `organizador.editar`): até 10 contas SMTP da empresa (`App\Support\SmtpEmpresa::MAXIMO`, era 5; o banco não limita: `Mail::build` na hora, senha
  `Crypt`, em branco mantém; "Enviar teste" grava `testado_em`/`ultimo_erro`; limite por hora). Só para divulgação: ingressos/senha/avisos
  continuam pelo fique@/noreply@. Cartão "E-mail marketing do Fora da Fila — em breve" (pacotes da Hostinger, 2º passo).
  **Divulgação → Listas de e-mail** (`/painel/listas-email`, `ListasEmailController`, `campanha.gerenciar`): criar lista, colar e-mails
  ("Nome; e-mail"), importar CSV (`;` ou `,`, cabeçalho email/nome/telefone ou coluna com @, UTF-8 ou Windows-1252 do Excel, até 10 MB),
  trazer compradores/participantes de eventos, busca, remover, exportar CSV, renomear/excluir; repetidos e inválidos contados.
  **Nome padronizado** ao gravar (decisão do Bruno, 09/11/2026; `ListasEmailController::padronizarNome`): "MARIA DA SILVA" → "Maria da Silva"
  (de/da/do/das/dos/e minúsculos no meio).
  `email_descadastro` (por empresa) marca "Descadastrado" e será pulado nos envios. Teste `fluxo-marketing.js` (16).
  **Declaração obrigatória** ao colar/importar/trazer de eventos (`ListasEmailController::DECLARACAO`, decisão do Bruno 09/11/2026), gravada
  no contato (`consentimento_em/_por`) e na auditoria (com IP).
  **Divulgação → Convite por e-mail** (V018, `/painel/convites`, `ConvitesController`, `App\Support\Convites`): evento publicado futuro + lista +
  conta SMTP + **assunto** (o resto vem do evento: imagem, saudação, data, local, resumo/descrição, botão "Garantir meu ingresso"); pré-visualização
  em iframe, "Enviar um teste para mim"; "Enviar para a lista" grava a foto da lista em `campanha_envio` (sem descadastrados) e a campanha fica `enviando`. **Cron** (`php artisan schedule:run` a cada minuto no hPanel → `convites:enviar`,
  `withoutOverlapping`): até 40 por conta por rodada, respeitando `smtp_conta.limite_hora` na última hora; marca antes de mandar (não duplica).
  Cabeçalhos `List-Unsubscribe` + One-Click; rodapé "Não quero mais receber" → `/descadastro/{org}?email=` (assinatura só do caminho,
  `DescadastroController`, sem CSRF) grava `email_descadastro`. Acompanhamento com barra, enviados/faltam/erros, Pausar (status `rascunho`),
  Retomar e Cancelar. Teste `fluxo-marketing.js` (32, com SMTP falso na porta 2526).
  **Grupo "Marketing" no menu** (decisão do Bruno, 09/11/2026): Convite por e-mail, **Agendamentos** (`/painel/convites/agendamentos`: fila de
  agendados/enviando/pausados com previsão de término e o quadro "Como funciona o envio" + última rodada do cron, cache `convites_ultima_rodada`),
  Listas de e-mail e **Contas de e-mail** (= Opções de marketing). Convite tem "Começar agora" ou **"Agendar"** (`agendada_para`, status `agendada`;
  a rodada do cron vira `enviando` quando chega a hora; tem de ser antes do fim do evento).
  **Limite diário por organização** (decisão do Bruno, 09/11/2026): `Convites::LIMITE_DIA` = **500 e-mails por dia por empresa**, divididos
  igualmente entre as contas ativas (`capacidade()`: 10 contas → 50 por conta/dia; vira à meia-noite de Brasília), além do limite por hora de cada
  conta. Convite com **"Todas as contas"** (`campanha.smtp_conta_id` NULL, padrão) usa todas; com uma conta, só a cota dela. A rodada é por empresa
  (`rodadaDaEmpresa`): cada conta pega pendentes na ordem dos convites; o que não sai hoje continua amanhã até acabar. **Repetição só dentro do mesmo
  convite** (UNIQUE campanha+e-mail): um novo convite do mesmo evento (outro assunto) manda de novo para todos. Agendamentos mostra "Hoje: X de 500",
  contas × cota e a previsão em dias (fila acumulada ÷ 500). Teste `fluxo-marketing.js` (37).
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
- **Cadastro único por CPF** (V011, decisão do Bruno 08/11/2026; `App\Support\CadastroPessoa`): `registrar()` grava/atualiza a pessoa
  sempre que alguém informa CPF + nome (participante da inscrição — com e-mail/telefone do comprador quando é ele mesmo —, representante
  legal da Organização, pagamento de teste da Administração); valores vazios não apagam os existentes; falhas não interrompem a operação.
  Busca `GET /cadastro-unico/{cpf}` **só para usuário logado da equipe** (throttle `cadastro-unico` 30/min, auditoria `pessoa.consulta`
  com os 4 últimos dígitos). **O CPF vem sempre primeiro no formulário**; como no CEP, ao completar os 11 números ou sair do campo (`data-busca-cpf`) preenche `data-pessoa="nome|email|telefone"` do mesmo formulário com o que encontrar.
  **No site público não há preenchimento pelo CPF** (qualquer um descobriria nome/e-mail de terceiros pelo CPF — risco LGPD). Pendente:
  citar o cadastro único no termo de privacidade e definir a base legal. Pagamento de teste usa **nome completo** (dividido em
  first_name/last_name para o Mercado Pago).
- **Formulários de inscrição** (V015, decisão do Bruno 08/11/2026; Gestão → Formulários, `FormulariosController`, `App\Support\Formularios`):
  a organização cria vários formulários (modelos `formulario.evento_id` NULL) marcando campos do catálogo (`campo_catalogo`: nome, CPF,
  telefone, endereço, camiseta, aceites…; pedir + obrigatório + rótulo) e campos personalizados (texto, texto longo, número, data, lista
  de opções separadas por ";", caixa de marcar). **Nome completo é sempre pedido** (um campo só, nunca nome e sobrenome separados).
  Padrão criado sozinho: **"Padrão — Nome completo e e-mail"** (ambos obrigatórios); "Tornar padrão"; não exclui o padrão, formulário
  em uso por evento nem com respostas; campo tirado fica `ativo = 0`. O evento escolhe o formulário (`evento.formulario_id`; vazio =
  padrão) — saíram as opções "Ingresso nominal" e "Exigir CPF" (`exige_cpf_participante` passa a ser "o formulário pede CPF
  obrigatório", para o limite por CPF). A compra (gratuita e paga) mostra os campos para cada participante, valida por tipo e grava:
  catálogo → coluna de mesmo nome em `participante`; personalizado → `participante_resposta` (`Formularios::criarParticipante`, também
  no `Checkout::aprovar`). Sem e-mail no formulário, o 1º participante recebe o do comprador. Testes antigos usam
  `tests/e2e/_formulario-legado.js` (nome obrigatório, e-mail e CPF opcionais); teste novo `fluxo-formularios.js` (17).
- **Escolhas em cartões** (decisão do Bruno, 08/11/2026): opções exclusivas (radio) usam `label.opcao-cartao` com `<span><b>título</b><small>descrição</small></span>` em `.grade grade-2` — nunca radio solto.
- **Regra de tela (decisão do Bruno, 08/11/2026): toda edição de item de lista abre ABAIXO da linha, em largura total, nunca ao
  lado.** Padrão: botão com `data-alternar="<id>"` + `<tr class="linha-acoes" id="<id>" hidden><td colspan="N">` (moldura cinza
  clara com borda laranja à esquerda, `public/css/app.css`). Usado em lotes, cupons, participantes, equipe e taxas da Administração.
- **E-mail sem acento** (08/11/2026: o login da Estação Slod foi gravado como "estaçãoslod@gmail.com" e nenhum e-mail chegava):
  toda validação de e-mail usa `email:filter` (recusa acento, cedilha e espaço). Administração → Empresas → detalhe avisa e-mail
  inválido e tem "Corrigir e-mail de login" (sugere a versão sem acento; `admin.empresas.email`, auditoria `admin.email_login_corrigido`).
- **Limites de tentativas (throttle):** sempre com nome próprio (`throttle:5,15,codigo-senha`), senão rotas diferentes
  dividem o mesmo contador.
- **Preferências de notificação** (por pessoa da equipe, `organizador_usuario`): `/painel/preferencias` —
  "Notificar-me cada vez que um participante realizar uma compra" (aviso por noreply@) e "Quero receber as
  novidades do Fora da Fila por e-mail" (consentimento com data em `receber_novidades_em`).
- `config/foradafila.php`: `comprador_cancela_gratuito` (padrão true; `FF_COMPRADOR_CANCELA_GRATUITO`).
- Testes de ponta a ponta: `tests/e2e/fluxo-produtor.js` (22), `fluxo-comprador.js` (30) e `fluxo-imagem.js` (18), `fluxo-organizacao.js` (30) e `fluxo-conta.js` (38) e `fluxo-evento-regras.js` (19, simula antes/depois do V005), `fluxo-endereco.js` (13), `fluxo-politica.js` (9), `fluxo-ingressos.js` (27), `fluxo-admin-empresas.js` (23), `fluxo-vendas.js` (23), `fluxo-checkin.js` (18), `fluxo-cupons.js` (17), `fluxo-divulgacao.js` (16), `fluxo-convidados.js` (19), `fluxo-mensagens.js` (13), `fluxo-taxa.js` (16), `fluxo-admin.js` (41, porta 8001), `fluxo-financeiro.js` (19, com Mercado Pago falso na porta 8099 e o Laravel em 8001 com as variáveis `MP_*` de teste do cabeçalho do arquivo) — os três últimos precisam do V004 —, com Playwright,
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
- **Assunto de e-mail de evento começa sempre pelo nome do evento** (decisão do Bruno, 09/11/2026): `Notificacoes::assuntoEvento($titulo, $assunto)`
  → "Nome do evento — resto" (tira o nome de onde estiver, inclusive de modelos editados pelo produtor); usado na confirmação, cancelamentos,
  aviso à equipe e comunicados.
- **Cópia para a organização** (decisão do Bruno, 09/11/2026): preferência "Receber cópia dos e-mails de ingressos" (Organização e Preferências,
  só com `organizador.editar`; `parametro_sistema` `copia_ingressos_org_<id>`, `App\Support\CopiaIngressos`). Um e-mail só ao comprador com **Cc**
  para `email_contato` (`Email` aceita `refs['cc']`); com cópia, o link "Crie a sua senha" não vai no e-mail.
- **E-mails de marketing não entram em `mensagem_envio`** (decisão do Bruno, 09/11/2026): convites, testes de conta SMTP e de convite saem por
  `SmtpEmpresa` direto e ficam só em `campanha_envio`; Administração → E-mails enviados mostra só os da plataforma (filtra `campanha_id IS NULL`).
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

- [x] Cron do agendador no hPanel criado em 09/11/2026 pela API (`* * * * *` `/opt/alt/php83/usr/bin/php .../public_html/artisan schedule:run`, uid JJ009pnPPs); hoje roda `convites:enviar`. Próximos: expirar reservas, virar lotes, lembretes
- [ ] Área administrativa da plataforma (equipe BH Software) — futuro próximo
- [ ] **Preços:** tabela de valores (o site não cita mais "1% no primeiro evento"; aguarda definição do Bruno): percentuais de
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
2. Publicar os apps de check-in nas lojas (`docs/apps-checkin.md`); check-in offline (fila local) se o Bruno pedir.
3. Confirmação de e-mail no cadastro e lembretes do evento.
