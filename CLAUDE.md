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
- **V002 (termos)**: precisa ser importado no phpMyAdmin — sem ele o cadastro de produtor não abre.
- Senha do usuário do banco trocada em 07/11/2026 por uma gerada (só existe no `.env.foradafila` do servidor).
- A aplicação conecta em `127.0.0.1:3306`. O host `*.hstgr.io` é só para acesso remoto liberado por IP.
- Senha nunca no código nem no Git: fica no `.env` do servidor, fora do `public_html`.
- Toda mudança de estrutura entra como nova migração versionada (`V002__...`), nunca por edição manual no phpMyAdmin.
- Convenções: InnoDB, utf8mb4, datas em UTC, dinheiro `DECIMAL(12,2)`, `id BIGINT` interno + `uuid` público,
  multi-tenant por `organizador_id` em toda tabela de negócio, exclusão lógica para dados financeiros.
- Triggers: `trg_conta_bu`, `trg_evento_bi`, `trg_evento_bu`, `trg_lote_bu`.

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
- Telas prontas: site (`/`), cadastro do produtor, login, convite da equipe, troca de organização, painel
  (dashboard), eventos (lista, criar, editar, publicar/pausar/encerrar/cancelar), tipos de ingresso e lotes,
  equipe, níveis de acesso (perfis próprios), dados da organização. Os demais módulos do menu mostram "em breve".
- Desenvolvimento local: MariaDB com V001+V002, `.env` local, `php artisan serve`. O `vendor/` vem da branch
  `publicacao` (o proxy da nuvem bloqueia downloads do Composer): `git archive origin/publicacao vendor | tar -x -C plataforma`.

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
- Projeto piloto: o Bruno vai cadastrar uma empresa dele como primeiro organizador. A conta nasce `ativo`
  (sem confirmação de e-mail enquanto não houver e-mail transacional).
- App de check-in como **PWA** (QR Code pela câmera, funciona offline), consumindo a mesma API.
- Rotinas automáticas (expirar reservas, virar lote, fila de e-mail, conciliação com o Mercado Pago) pelo
  agendador do Laravel, chamado por um cron a cada minuto no hPanel.
- Os models do Laravel devem mapear as tabelas que **já existem** (não recriar via migrations do Laravel);
  escopo global por `organizador_id`.

## Pendências

- [ ] Importar `banco/V002__termos_iniciais.sql` em produção
- [ ] Configurar o cron do agendador no hPanel (expirar reservas, virar lotes) quando houver vendas
- [ ] Área administrativa da plataforma (equipe BH Software) — futuro próximo
- [ ] Decidir o modo de cobrança (split ou fatura) e os valores: percentual, fixo e mínimo por ingresso
- [ ] Confirmar com o Mercado Pago se dá para reter a comissão na venda (split / marketplace)
- [ ] Redigir com advogado os 4 termos (uso, responsabilidade do organizador, compra, privacidade)
- [ ] Validar a política de cancelamento, o direito de arrependimento e a cota de meia-entrada
- [ ] Escolher o provedor de e-mail transacional (fila `mensagem_envio`)
- [ ] Criar um banco de homologação

## Próximos passos

1. Financeiro: conectar e homologar a conta do Mercado Pago (OAuth), para liberar ingressos pagos.
2. Página pública do evento e checkout (vitrine `foradafila.com.br/e/<slug>`).
3. Participantes, pedidos e check-in (PWA).
