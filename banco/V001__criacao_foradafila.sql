-- =====================================================================
-- Fora da Fila — V001: estrutura completa (revisão 2)
-- Banco : u839930205_foradafila (MySQL / Hostinger)
-- Regras: doc "Fora da Fila — Regras de Negócio do Banco de Dados"
-- Convenções: InnoDB, utf8mb4, datas em UTC, dinheiro DECIMAL(12,2),
--             id BIGINT interno + uuid público, multi-tenant por organizador_id.
-- Compatível com MySQL 8.0.16+ e MariaDB 10.5+.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =====================================================================
-- MÓDULO 1 — PLATAFORMA (catálogos do sistema, termos, auditoria)
-- =====================================================================
CREATE TABLE parametro_sistema (
  chave            VARCHAR(80)     NOT NULL,
  valor            VARCHAR(500)    NOT NULL,
  descricao        VARCHAR(255)    NULL,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE termo (
  id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  tipo             ENUM('uso_organizador','responsabilidade_organizador','compra_comprador','privacidade') NOT NULL,
  versao           VARCHAR(10)     NOT NULL,
  titulo           VARCHAR(150)    NOT NULL,
  conteudo_html    MEDIUMTEXT      NOT NULL,
  vigente_desde    DATETIME        NOT NULL,
  vigente          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Só uma versão vigente por tipo (regra da aplicação)',
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_termo_tipo_versao (tipo, versao),
  KEY ix_termo_vigente (tipo, vigente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evento_categoria (
  id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome             VARCHAR(60)     NOT NULL,
  slug             VARCHAR(70)     NOT NULL,
  ativo            TINYINT(1)      NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categoria_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gateway (
  id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo           VARCHAR(30)     NOT NULL,
  nome             VARCHAR(60)     NOT NULL,
  suporta_split    TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Retém a comissão da plataforma na própria transação',
  suporta_pix      TINYINT(1)      NOT NULL DEFAULT 0,
  suporta_cartao   TINYINT(1)      NOT NULL DEFAULT 0,
  suporta_boleto   TINYINT(1)      NOT NULL DEFAULT 0,
  ativo            TINYINT(1)      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gateway_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissao (
  id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo           VARCHAR(60)     NOT NULL COMMENT 'modulo.acao',
  modulo           VARCHAR(30)     NOT NULL,
  descricao        VARCHAR(150)    NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissao_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE campo_catalogo (
  id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo           VARCHAR(50)     NOT NULL COMMENT 'Igual ao nome da coluna em participante',
  rotulo_padrao    VARCHAR(100)    NOT NULL,
  tipo             ENUM('texto','email','telefone','cpf','documento','data','selecao','cep','uf','aceite') NOT NULL,
  grupo            ENUM('identificacao','contato','endereco','perfil','evento','aceites') NOT NULL,
  opcoes           JSON            NULL COMMENT 'Para tipo selecao',
  sensivel         TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Dado pessoal sensível (LGPD art. 5º II)',
  sempre_obrigatorio TINYINT(1)    NOT NULL DEFAULT 0,
  ordem            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campo_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MÓDULO 2 — ORGANIZADOR (área do produtor)
-- =====================================================================
CREATE TABLE organizador (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid             CHAR(36)        NOT NULL,
  tipo_pessoa      ENUM('PF','PJ') NOT NULL,
  documento        VARCHAR(14)     NOT NULL COMMENT 'CPF ou CNPJ, só dígitos',
  razao_social     VARCHAR(150)    NOT NULL,
  nome_fantasia    VARCHAR(150)    NULL,
  slug             VARCHAR(80)     NOT NULL COMMENT 'foradafila.com.br/p/<slug>',
  logo_url         VARCHAR(255)    NULL,
  descricao        TEXT            NULL,
  site             VARCHAR(255)    NULL,
  instagram        VARCHAR(60)     NULL,
  whatsapp         VARCHAR(20)     NULL,
  email_contato    VARCHAR(150)    NOT NULL COMMENT 'SAC exibido ao comprador',
  email_financeiro VARCHAR(150)    NOT NULL,
  cep              CHAR(8)         NULL,
  logradouro       VARCHAR(150)    NULL,
  numero           VARCHAR(20)     NULL,
  complemento      VARCHAR(60)     NULL,
  bairro           VARCHAR(100)    NULL,
  cidade           VARCHAR(100)    NULL,
  uf               CHAR(2)         NULL,
  situacao         ENUM('pendente','ativo','bloqueado_debito','suspenso','encerrado') NOT NULL DEFAULT 'pendente',
  situacao_motivo  VARCHAR(255)    NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_organizador_uuid (uuid),
  UNIQUE KEY uq_organizador_documento (documento),
  UNIQUE KEY uq_organizador_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MÓDULO 3 — USUÁRIOS E NÍVEIS DE ACESSO
-- =====================================================================
CREATE TABLE usuario (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid             CHAR(36)        NOT NULL,
  nome             VARCHAR(150)    NOT NULL,
  email            VARCHAR(150)    NOT NULL,
  telefone         VARCHAR(20)     NULL,
  email_verificado_em DATETIME     NULL,
  senha_hash       VARCHAR(255)    NULL COMMENT 'Argon2id/bcrypt; NULL até aceitar convite',
  totp_segredo_cript VARBINARY(255) NULL COMMENT '2FA, criptografado',
  admin_plataforma TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Equipe BHSoftware',
  ativo            TINYINT(1)      NOT NULL DEFAULT 1,
  tentativas_login SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_ate    DATETIME        NULL,
  ultimo_login_em  DATETIME        NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuario_uuid (uuid),
  UNIQUE KEY uq_usuario_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE perfil_acesso (
  id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NULL COMMENT 'NULL = perfil padrão do sistema',
  codigo           VARCHAR(30)     NOT NULL,
  nome             VARCHAR(60)     NOT NULL,
  descricao        VARCHAR(255)    NULL,
  editavel         TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'Perfil Dono não é editável',
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_perfil_org_codigo (organizador_id, codigo),
  CONSTRAINT fk_perfil_organizador FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE perfil_permissao (
  perfil_id        INT UNSIGNED    NOT NULL,
  permissao_id     SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (perfil_id, permissao_id),
  CONSTRAINT fk_pp_perfil    FOREIGN KEY (perfil_id)    REFERENCES perfil_acesso(id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_permissao FOREIGN KEY (permissao_id) REFERENCES permissao(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE organizador_usuario (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  usuario_id       BIGINT UNSIGNED NOT NULL,
  perfil_id        INT UNSIGNED    NOT NULL,
  todos_eventos    TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '0 = só os eventos de organizador_usuario_evento',
  status           ENUM('convidado','ativo','suspenso','removido') NOT NULL DEFAULT 'convidado',
  convite_token_hash CHAR(64)      NULL,
  convite_expira_em DATETIME       NULL,
  convidado_por    BIGINT UNSIGNED NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ou (organizador_id, usuario_id),
  KEY ix_ou_usuario (usuario_id),
  CONSTRAINT fk_ou_organizador FOREIGN KEY (organizador_id) REFERENCES organizador(id)  ON DELETE RESTRICT,
  CONSTRAINT fk_ou_usuario     FOREIGN KEY (usuario_id)     REFERENCES usuario(id)      ON DELETE RESTRICT,
  CONSTRAINT fk_ou_perfil      FOREIGN KEY (perfil_id)      REFERENCES perfil_acesso(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ou_convidado_por FOREIGN KEY (convidado_por) REFERENCES usuario(id)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE termo_aceite (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  termo_id         INT UNSIGNED    NOT NULL,
  organizador_id   BIGINT UNSIGNED NULL,
  usuario_id       BIGINT UNSIGNED NULL,
  comprador_id     BIGINT UNSIGNED NULL,
  ip               VARCHAR(45)     NOT NULL,
  user_agent       VARCHAR(255)    NULL,
  aceito_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_aceite_org (organizador_id, termo_id),
  KEY ix_aceite_comprador (comprador_id),
  CONSTRAINT fk_aceite_termo FOREIGN KEY (termo_id)       REFERENCES termo(id)       ON DELETE RESTRICT,
  CONSTRAINT fk_aceite_org   FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_aceite_usu   FOREIGN KEY (usuario_id)     REFERENCES usuario(id)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MÓDULO 4 — FINANCEIRO DO ORGANIZADOR (contas de recebimento)
-- =====================================================================
CREATE TABLE conta_pagamento (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid               CHAR(36)        NOT NULL,
  organizador_id     BIGINT UNSIGNED NOT NULL,
  gateway_id         SMALLINT UNSIGNED NOT NULL,
  apelido            VARCHAR(60)     NOT NULL COMMENT 'Ex.: Mercado Pago - Choperia',
  ambiente           ENUM('sandbox','producao') NOT NULL DEFAULT 'sandbox',
  status             ENUM('pendente','conectada','em_homologacao','homologada','ativa','suspensa','revogada')
                                     NOT NULL DEFAULT 'pendente',
  conta_externa_id   VARCHAR(100)    NULL COMMENT 'user_id/collector_id no gateway',
  email_conta        VARCHAR(150)    NULL,
  public_key         VARCHAR(255)    NULL,
  access_token_cript VARBINARY(2048) NULL COMMENT 'AES-256-GCM, chave fora do banco',
  refresh_token_cript VARBINARY(2048) NULL,
  token_expira_em    DATETIME        NULL,
  meios_habilitados  SET('pix','cartao','boleto') NOT NULL DEFAULT 'pix,cartao',
  parcelas_max       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  juros_parcelamento ENUM('organizador','comprador') NOT NULL DEFAULT 'organizador',
  pix_expira_min     SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  descricao_fatura   VARCHAR(22)     NULL COMMENT 'Nome na fatura do cartão',
  padrao             TINYINT(1)      NOT NULL DEFAULT 0,
  homologada_em      DATETIME        NULL,
  ativada_em         DATETIME        NULL,
  criado_em          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_conta_uuid (uuid),
  KEY ix_conta_org (organizador_id, status),
  CONSTRAINT fk_conta_organizador FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_conta_gateway     FOREIGN KEY (gateway_id)     REFERENCES gateway(id)     ON DELETE RESTRICT,
  CONSTRAINT ck_conta_parcelas CHECK (parcelas_max BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE homologacao_teste (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conta_pagamento_id BIGINT UNSIGNED NOT NULL,
  teste              ENUM('conexao_oauth','pix_aprovado','cartao_aprovado','cartao_recusado',
                          'webhook_recebido','reembolso','split_comissao') NOT NULL,
  resultado          ENUM('ok','falha') NOT NULL,
  detalhe            JSON            NULL,
  executado_por      BIGINT UNSIGNED NULL,
  executado_em       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_homolog_conta (conta_pagamento_id, teste),
  CONSTRAINT fk_homolog_conta FOREIGN KEY (conta_pagamento_id) REFERENCES conta_pagamento(id) ON DELETE RESTRICT,
  CONSTRAINT fk_homolog_usu   FOREIGN KEY (executado_por)      REFERENCES usuario(id)         ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MÓDULO 5 — EVENTOS, TIPOS DE INGRESSO E LOTES
-- =====================================================================
CREATE TABLE local (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  nome             VARCHAR(150)    NOT NULL,
  cep              CHAR(8)         NULL,
  logradouro       VARCHAR(150)    NULL,
  numero           VARCHAR(20)     NULL,
  complemento      VARCHAR(60)     NULL,
  bairro           VARCHAR(100)    NULL,
  cidade           VARCHAR(100)    NOT NULL,
  uf               CHAR(2)         NOT NULL,
  latitude         DECIMAL(9,6)    NULL,
  longitude        DECIMAL(9,6)    NULL,
  capacidade       INT UNSIGNED    NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_local_org (organizador_id),
  CONSTRAINT fk_local_organizador FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evento (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid                  CHAR(36)        NOT NULL,
  organizador_id        BIGINT UNSIGNED NOT NULL,
  local_id              BIGINT UNSIGNED NULL,
  categoria_id          SMALLINT UNSIGNED NULL,
  conta_pagamento_id    BIGINT UNSIGNED NULL COMMENT 'Conta que recebe as vendas deste evento',
  titulo                VARCHAR(150)    NOT NULL,
  slug                  VARCHAR(170)    NOT NULL,
  resumo                VARCHAR(300)    NULL,
  descricao             MEDIUMTEXT      NULL,
  imagem_capa_url       VARCHAR(255)    NULL,
  formato               ENUM('presencial','online','hibrido') NOT NULL DEFAULT 'presencial',
  link_online           VARCHAR(255)    NULL COMMENT 'Enviado só a quem tem ingresso',
  classificacao         ENUM('livre','10','12','14','16','18') NOT NULL DEFAULT 'livre',
  data_inicio           DATETIME        NOT NULL,
  data_fim              DATETIME        NOT NULL,
  abertura_portoes      DATETIME        NULL,
  capacidade            INT UNSIGNED    NOT NULL,
  visibilidade          ENUM('publico','nao_listado','privado') NOT NULL DEFAULT 'publico',
  status                ENUM('rascunho','publicado','pausado','encerrado','cancelado') NOT NULL DEFAULT 'rascunho',
  max_ingressos_pedido  SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  max_ingressos_cpf     SMALLINT UNSIGNED NULL COMMENT 'NULL = sem limite por CPF',
  exige_cpf_participante TINYINT(1)     NOT NULL DEFAULT 1,
  nominal               TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'Cada ingresso tem titular',
  permite_transferencia TINYINT(1)      NOT NULL DEFAULT 1,
  transferencia_ate_h   SMALLINT UNSIGNED NOT NULL DEFAULT 24 COMMENT 'Horas antes do início',
  permite_reentrada     TINYINT(1)      NOT NULL DEFAULT 0,
  politica_cancelamento TEXT            NULL,
  ultimo_numero_ingresso INT UNSIGNED   NOT NULL DEFAULT 0 COMMENT 'Contador do número sequencial',
  publicado_em          DATETIME        NULL,
  cancelado_em          DATETIME        NULL,
  cancelamento_motivo   VARCHAR(500)    NULL,
  criado_por            BIGINT UNSIGNED NULL,
  criado_em             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_evento_uuid (uuid),
  UNIQUE KEY uq_evento_slug (slug),
  KEY ix_evento_org_status (organizador_id, status, data_inicio),
  KEY ix_evento_vitrine (status, visibilidade, data_inicio),
  CONSTRAINT fk_evento_organizador FOREIGN KEY (organizador_id)     REFERENCES organizador(id)      ON DELETE RESTRICT,
  CONSTRAINT fk_evento_local       FOREIGN KEY (local_id)           REFERENCES local(id)            ON DELETE RESTRICT,
  CONSTRAINT fk_evento_categoria   FOREIGN KEY (categoria_id)       REFERENCES evento_categoria(id) ON DELETE RESTRICT,
  CONSTRAINT fk_evento_conta       FOREIGN KEY (conta_pagamento_id) REFERENCES conta_pagamento(id)  ON DELETE RESTRICT,
  CONSTRAINT fk_evento_criado_por  FOREIGN KEY (criado_por)         REFERENCES usuario(id)          ON DELETE RESTRICT,
  CONSTRAINT ck_evento_datas      CHECK (data_fim > data_inicio),
  CONSTRAINT ck_evento_capacidade CHECK (capacidade > 0),
  CONSTRAINT ck_evento_max_pedido CHECK (max_ingressos_pedido > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE organizador_usuario_evento (
  organizador_usuario_id BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (organizador_usuario_id, evento_id),
  KEY ix_oue_evento (evento_id),
  CONSTRAINT fk_oue_ou     FOREIGN KEY (organizador_usuario_id) REFERENCES organizador_usuario(id) ON DELETE CASCADE,
  CONSTRAINT fk_oue_evento FOREIGN KEY (evento_id)              REFERENCES evento(id)              ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ingresso_tipo (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NOT NULL,
  nome             VARCHAR(80)     NOT NULL COMMENT 'Ex.: Pista, VIP, Camarote',
  descricao        VARCHAR(500)    NULL,
  capacidade       INT UNSIGNED    NULL COMMENT 'Limite do setor; NULL = só a do evento',
  ordem            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ativo            TINYINT(1)      NOT NULL DEFAULT 1,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_it_evento (evento_id),
  CONSTRAINT fk_it_organizador FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_it_evento      FOREIGN KEY (evento_id)      REFERENCES evento(id)      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lote (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid             CHAR(36)        NOT NULL,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NOT NULL,
  ingresso_tipo_id BIGINT UNSIGNED NOT NULL,
  lote_anterior_id BIGINT UNSIGNED NULL COMMENT 'Abre quando o anterior esgota/encerra',
  nome             VARCHAR(80)     NOT NULL COMMENT 'Ex.: 1º lote',
  modalidade       ENUM('inteira','meia','cortesia') NOT NULL DEFAULT 'inteira',
  preco            DECIMAL(12,2)   NOT NULL,
  quantidade       INT UNSIGNED    NOT NULL,
  reservado        INT UNSIGNED    NOT NULL DEFAULT 0,
  vendido          INT UNSIGNED    NOT NULL DEFAULT 0,
  min_por_pedido   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  max_por_pedido   SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  venda_inicio     DATETIME        NOT NULL,
  venda_fim        DATETIME        NOT NULL,
  virada           ENUM('manual','ao_esgotar','por_data','esgotar_ou_data') NOT NULL DEFAULT 'esgotar_ou_data',
  visibilidade     ENUM('publico','oculto','convidados') NOT NULL DEFAULT 'publico'
                   COMMENT 'oculto = só por link de acesso privado',
  status           ENUM('agendado','ativo','pausado','esgotado','encerrado') NOT NULL DEFAULT 'agendado',
  ordem            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lote_uuid (uuid),
  KEY ix_lote_evento (evento_id, status, visibilidade),
  KEY ix_lote_tipo (ingresso_tipo_id),
  KEY ix_lote_org (organizador_id),
  CONSTRAINT fk_lote_organizador FOREIGN KEY (organizador_id)   REFERENCES organizador(id)   ON DELETE RESTRICT,
  CONSTRAINT fk_lote_evento      FOREIGN KEY (evento_id)        REFERENCES evento(id)        ON DELETE RESTRICT,
  CONSTRAINT fk_lote_tipo        FOREIGN KEY (ingresso_tipo_id) REFERENCES ingresso_tipo(id) ON DELETE RESTRICT,
  CONSTRAINT fk_lote_anterior    FOREIGN KEY (lote_anterior_id) REFERENCES lote(id)          ON DELETE RESTRICT,
  CONSTRAINT ck_lote_preco    CHECK (preco >= 0),
  CONSTRAINT ck_lote_cortesia CHECK (modalidade <> 'cortesia' OR preco = 0),
  CONSTRAINT ck_lote_qtd      CHECK (quantidade > 0),
  CONSTRAINT ck_lote_estoque  CHECK (reservado + vendido <= quantidade),
  CONSTRAINT ck_lote_janela   CHECK (venda_fim > venda_inicio),
  CONSTRAINT ck_lote_por_pedido CHECK (min_por_pedido >= 1 AND max_por_pedido >= min_por_pedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MÓDULO 6 — FORMULÁRIO DE INSCRIÇÃO CONFIGURÁVEL
-- =====================================================================
CREATE TABLE formulario (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NULL COMMENT 'NULL = modelo reutilizável do organizador',
  nome             VARCHAR(80)     NOT NULL,
  texto_termo_responsabilidade MEDIUMTEXT NULL COMMENT 'Texto que o participante marca como lido',
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_formulario_evento (evento_id),
  KEY ix_formulario_org (organizador_id),
  CONSTRAINT fk_form_organizador FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_form_evento      FOREIGN KEY (evento_id)      REFERENCES evento(id)      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE formulario_campo (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  formulario_id    BIGINT UNSIGNED NOT NULL,
  campo_catalogo_id SMALLINT UNSIGNED NULL COMMENT 'NULL = campo personalizado',
  codigo_personalizado VARCHAR(50) NULL,
  rotulo           VARCHAR(120)    NOT NULL,
  ajuda            VARCHAR(255)    NULL,
  tipo_personalizado ENUM('texto','texto_longo','numero','data','selecao','multipla','checkbox') NULL,
  opcoes           JSON            NULL,
  obrigatorio      TINYINT(1)      NOT NULL DEFAULT 0,
  ingresso_tipo_id BIGINT UNSIGNED NULL COMMENT 'NULL = todos os tipos de ingresso',
  ordem            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ativo            TINYINT(1)      NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fc_catalogo (formulario_id, campo_catalogo_id, ingresso_tipo_id),
  UNIQUE KEY uq_fc_personalizado (formulario_id, codigo_personalizado),
  CONSTRAINT fk_fc_formulario FOREIGN KEY (formulario_id)     REFERENCES formulario(id)     ON DELETE CASCADE,
  CONSTRAINT fk_fc_catalogo   FOREIGN KEY (campo_catalogo_id) REFERENCES campo_catalogo(id) ON DELETE RESTRICT,
  CONSTRAINT fk_fc_tipo       FOREIGN KEY (ingresso_tipo_id)  REFERENCES ingresso_tipo(id)  ON DELETE RESTRICT,
  CONSTRAINT ck_fc_origem CHECK (
    (campo_catalogo_id IS NOT NULL AND codigo_personalizado IS NULL AND tipo_personalizado IS NULL)
    OR (campo_catalogo_id IS NULL AND codigo_personalizado IS NOT NULL AND tipo_personalizado IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MÓDULO 7 — DIVULGAÇÃO COMERCIAL: PROMOTERS E CUPONS
-- =====================================================================
CREATE TABLE promoter (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NULL COMMENT 'NULL = vale para todos os eventos do organizador',
  usuario_id       BIGINT UNSIGNED NULL COMMENT 'Se o promoter acompanha as próprias vendas',
  nome             VARCHAR(150)    NOT NULL,
  email            VARCHAR(150)    NULL,
  telefone         VARCHAR(20)     NULL,
  codigo_link      VARCHAR(40)     NOT NULL COMMENT '...?ref=<codigo_link>',
  utm_source       VARCHAR(60)     NULL,
  utm_campaign     VARCHAR(60)     NULL,
  cupom_id         BIGINT UNSIGNED NULL COMMENT 'Cupom aplicado automaticamente pelo link',
  cliques          INT UNSIGNED    NOT NULL DEFAULT 0,
  ativo            TINYINT(1)      NOT NULL DEFAULT 1,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_promoter_link (organizador_id, codigo_link),
  CONSTRAINT fk_promoter_org    FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_promoter_evento FOREIGN KEY (evento_id)      REFERENCES evento(id)      ON DELETE RESTRICT,
  CONSTRAINT fk_promoter_usu    FOREIGN KEY (usuario_id)     REFERENCES usuario(id)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cupom (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NULL COMMENT 'NULL = todos os eventos do organizador',
  promoter_id      BIGINT UNSIGNED NULL,
  codigo           VARCHAR(30)     NOT NULL COMMENT 'Gravado em maiúsculas',
  descricao        VARCHAR(150)    NULL,
  tipo_desconto    ENUM('percentual','valor_fixo') NOT NULL,
  valor            DECIMAL(12,2)   NOT NULL,
  aplica_em        ENUM('ingresso','pedido') NOT NULL DEFAULT 'ingresso',
  valor_minimo_pedido DECIMAL(12,2) NULL,
  max_usos         INT UNSIGNED    NULL COMMENT 'NULL = ilimitado',
  max_usos_cpf     SMALLINT UNSIGNED NULL,
  usos             INT UNSIGNED    NOT NULL DEFAULT 0,
  valido_de        DATETIME        NULL,
  valido_ate       DATETIME        NULL,
  ativo            TINYINT(1)      NOT NULL DEFAULT 1,
  criado_por       BIGINT UNSIGNED NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cupom_codigo (organizador_id, codigo),
  KEY ix_cupom_evento (evento_id),
  CONSTRAINT fk_cupom_org      FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cupom_evento   FOREIGN KEY (evento_id)      REFERENCES evento(id)      ON DELETE RESTRICT,
  CONSTRAINT fk_cupom_promoter FOREIGN KEY (promoter_id)    REFERENCES promoter(id)    ON DELETE RESTRICT,
  CONSTRAINT fk_cupom_usu      FOREIGN KEY (criado_por)     REFERENCES usuario(id)     ON DELETE RESTRICT,
  CONSTRAINT ck_cupom_valor CHECK (valor > 0 AND (tipo_desconto <> 'percentual' OR valor <= 100)),
  CONSTRAINT ck_cupom_usos  CHECK (max_usos IS NULL OR usos <= max_usos),
  CONSTRAINT ck_cupom_datas CHECK (valido_ate IS NULL OR valido_de IS NULL OR valido_ate > valido_de)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE promoter
  ADD CONSTRAINT fk_promoter_cupom FOREIGN KEY (cupom_id) REFERENCES cupom(id) ON DELETE RESTRICT;

CREATE TABLE cupom_lote (
  cupom_id         BIGINT UNSIGNED NOT NULL,
  lote_id          BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (cupom_id, lote_id),
  KEY ix_cl_lote (lote_id),
  CONSTRAINT fk_cl_cupom FOREIGN KEY (cupom_id) REFERENCES cupom(id) ON DELETE CASCADE,
  CONSTRAINT fk_cl_lote  FOREIGN KEY (lote_id)  REFERENCES lote(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MÓDULO 8 — CONVIDADOS E ACESSO PRIVADO
-- =====================================================================
CREATE TABLE acesso_privado (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NOT NULL,
  nome_canal       VARCHAR(80)     NOT NULL COMMENT 'Ex.: Grupo WhatsApp sócios',
  token            CHAR(32)        NOT NULL COMMENT 'foradafila.com.br/e/<slug>?acesso=<token>',
  senha_hash       VARCHAR(255)    NULL COMMENT 'Senha opcional além do link',
  max_usos         INT UNSIGNED    NULL,
  usos             INT UNSIGNED    NOT NULL DEFAULT 0,
  valido_ate       DATETIME        NULL,
  ativo            TINYINT(1)      NOT NULL DEFAULT 1,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_acesso_token (token),
  KEY ix_acesso_evento (evento_id),
  CONSTRAINT fk_acesso_org    FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_acesso_evento FOREIGN KEY (evento_id)      REFERENCES evento(id)      ON DELETE RESTRICT,
  CONSTRAINT ck_acesso_usos CHECK (max_usos IS NULL OR usos <= max_usos)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE acesso_privado_lote (
  acesso_privado_id BIGINT UNSIGNED NOT NULL,
  lote_id          BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (acesso_privado_id, lote_id),
  KEY ix_apl_lote (lote_id),
  CONSTRAINT fk_apl_acesso FOREIGN KEY (acesso_privado_id) REFERENCES acesso_privado(id) ON DELETE CASCADE,
  CONSTRAINT fk_apl_lote   FOREIGN KEY (lote_id)           REFERENCES lote(id)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lista_convidados (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NOT NULL,
  lote_id          BIGINT UNSIGNED NOT NULL COMMENT 'Lote (cortesia ou pago) usado para emitir',
  nome             VARCHAR(80)     NOT NULL COMMENT 'Ex.: Lista VIP, Imprensa, Aniversariantes',
  responsavel_id   BIGINT UNSIGNED NULL,
  limite_convidados INT UNSIGNED   NULL,
  max_acompanhantes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  confirmar_ate    DATETIME        NULL,
  ativo            TINYINT(1)      NOT NULL DEFAULT 1,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_lc_evento (evento_id),
  CONSTRAINT fk_lc_org    FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_lc_evento FOREIGN KEY (evento_id)      REFERENCES evento(id)      ON DELETE RESTRICT,
  CONSTRAINT fk_lc_lote   FOREIGN KEY (lote_id)        REFERENCES lote(id)        ON DELETE RESTRICT,
  CONSTRAINT fk_lc_resp   FOREIGN KEY (responsavel_id) REFERENCES usuario(id)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MÓDULO 9 — COMPRADORES, PEDIDOS, INGRESSOS E PARTICIPANTES
-- =====================================================================
CREATE TABLE comprador (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid             CHAR(36)        NOT NULL,
  nome             VARCHAR(150)    NOT NULL,
  email            VARCHAR(150)    NOT NULL,
  cpf              CHAR(11)        NULL,
  telefone         VARCHAR(20)     NULL,
  data_nascimento  DATE            NULL,
  senha_hash       VARCHAR(255)    NULL COMMENT 'Opcional: conta "Meus ingressos"; senão login por link no e-mail',
  email_verificado_em DATETIME     NULL,
  anonimizado_em   DATETIME        NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_comprador_uuid (uuid),
  UNIQUE KEY uq_comprador_email (email),
  KEY ix_comprador_cpf (cpf)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE termo_aceite
  ADD CONSTRAINT fk_aceite_comprador FOREIGN KEY (comprador_id) REFERENCES comprador(id) ON DELETE RESTRICT;

CREATE TABLE convidado (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lista_id         BIGINT UNSIGNED NOT NULL,
  nome             VARCHAR(150)    NOT NULL,
  email            VARCHAR(150)    NULL,
  telefone         VARCHAR(20)     NULL,
  cpf              CHAR(11)        NULL,
  acompanhantes    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  token_hash       CHAR(64)        NOT NULL COMMENT 'Link pessoal de confirmação',
  status           ENUM('pendente','enviado','confirmado','emitido','recusado','cancelado') NOT NULL DEFAULT 'pendente',
  pedido_id        BIGINT UNSIGNED NULL COMMENT 'Pedido de cortesia gerado na emissão',
  adicionado_por   BIGINT UNSIGNED NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_convidado_token (token_hash),
  UNIQUE KEY uq_convidado_cpf (lista_id, cpf),
  KEY ix_convidado_lista (lista_id, status),
  CONSTRAINT fk_conv_lista FOREIGN KEY (lista_id)       REFERENCES lista_convidados(id) ON DELETE RESTRICT,
  CONSTRAINT fk_conv_usu   FOREIGN KEY (adicionado_por) REFERENCES usuario(id)          ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pedido (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid                 CHAR(36)        NOT NULL,
  codigo               VARCHAR(12)     NOT NULL COMMENT 'Número do pedido exibido (ex.: FF7K2M9Q)',
  organizador_id       BIGINT UNSIGNED NOT NULL,
  evento_id            BIGINT UNSIGNED NOT NULL,
  comprador_id         BIGINT UNSIGNED NOT NULL,
  origem               ENUM('site','link_promoter','acesso_privado','lista_convidados','emissao_manual') NOT NULL DEFAULT 'site',
  promoter_id          BIGINT UNSIGNED NULL,
  cupom_id             BIGINT UNSIGNED NULL,
  acesso_privado_id    BIGINT UNSIGNED NULL,
  chave_idempotencia   CHAR(36)        NOT NULL,
  status               ENUM('reservado','aguardando_pagamento','pago','expirado','recusado',
                            'cancelado','reembolsado','reembolso_parcial','contestado') NOT NULL DEFAULT 'reservado',
  valor_bruto          DECIMAL(12,2)   NOT NULL COMMENT 'Soma dos itens sem desconto',
  valor_desconto       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  valor_total          DECIMAL(12,2)   NOT NULL COMMENT 'Valor pago pelo comprador',
  conta_pagamento_id   BIGINT UNSIGNED NULL,
  metodo_pagamento     ENUM('pix','cartao','boleto','gratuito') NULL,
  utm_source           VARCHAR(60)     NULL,
  utm_medium           VARCHAR(60)     NULL,
  utm_campaign         VARCHAR(60)     NULL,
  ip                   VARCHAR(45)     NULL,
  expira_em            DATETIME        NOT NULL,
  pago_em              DATETIME        NULL,
  cancelado_em         DATETIME        NULL,
  criado_em            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pedido_uuid (uuid),
  UNIQUE KEY uq_pedido_codigo (codigo),
  UNIQUE KEY uq_pedido_idempotencia (chave_idempotencia),
  KEY ix_pedido_org_status (organizador_id, status, pago_em),
  KEY ix_pedido_evento (evento_id, status),
  KEY ix_pedido_expira (status, expira_em),
  KEY ix_pedido_comprador (comprador_id),
  KEY ix_pedido_promoter (promoter_id),
  KEY ix_pedido_cupom (cupom_id),
  CONSTRAINT fk_pedido_organizador FOREIGN KEY (organizador_id)     REFERENCES organizador(id)     ON DELETE RESTRICT,
  CONSTRAINT fk_pedido_evento      FOREIGN KEY (evento_id)          REFERENCES evento(id)          ON DELETE RESTRICT,
  CONSTRAINT fk_pedido_comprador   FOREIGN KEY (comprador_id)       REFERENCES comprador(id)       ON DELETE RESTRICT,
  CONSTRAINT fk_pedido_promoter    FOREIGN KEY (promoter_id)        REFERENCES promoter(id)        ON DELETE RESTRICT,
  CONSTRAINT fk_pedido_cupom       FOREIGN KEY (cupom_id)           REFERENCES cupom(id)           ON DELETE RESTRICT,
  CONSTRAINT fk_pedido_acesso      FOREIGN KEY (acesso_privado_id)  REFERENCES acesso_privado(id)  ON DELETE RESTRICT,
  CONSTRAINT fk_pedido_conta       FOREIGN KEY (conta_pagamento_id) REFERENCES conta_pagamento(id) ON DELETE RESTRICT,
  CONSTRAINT ck_pedido_valores CHECK (valor_bruto >= 0 AND valor_desconto >= 0
                                      AND valor_total = valor_bruto - valor_desconto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE convidado
  ADD CONSTRAINT fk_conv_pedido FOREIGN KEY (pedido_id) REFERENCES pedido(id) ON DELETE RESTRICT;

CREATE TABLE pedido_item (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pedido_id        BIGINT UNSIGNED NOT NULL,
  lote_id          BIGINT UNSIGNED NOT NULL,
  quantidade       SMALLINT UNSIGNED NOT NULL,
  preco_unitario   DECIMAL(12,2)   NOT NULL COMMENT 'Congelado na compra',
  desconto_unitario DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  subtotal         DECIMAL(12,2)   NOT NULL,
  PRIMARY KEY (id),
  KEY ix_item_pedido (pedido_id),
  KEY ix_item_lote (lote_id),
  CONSTRAINT fk_item_pedido FOREIGN KEY (pedido_id) REFERENCES pedido(id) ON DELETE RESTRICT,
  CONSTRAINT fk_item_lote   FOREIGN KEY (lote_id)   REFERENCES lote(id)   ON DELETE RESTRICT,
  CONSTRAINT ck_item_qtd      CHECK (quantidade > 0),
  CONSTRAINT ck_item_preco    CHECK (preco_unitario >= 0 AND desconto_unitario >= 0 AND desconto_unitario <= preco_unitario),
  CONSTRAINT ck_item_subtotal CHECK (subtotal = quantidade * (preco_unitario - desconto_unitario))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cupom_uso (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cupom_id         BIGINT UNSIGNED NOT NULL,
  pedido_id        BIGINT UNSIGNED NOT NULL,
  cpf              CHAR(11)        NULL,
  valor_desconto   DECIMAL(12,2)   NOT NULL,
  status           ENUM('reservado','confirmado','estornado') NOT NULL DEFAULT 'reservado',
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cupom_uso_pedido (pedido_id),
  KEY ix_cupom_uso_cpf (cupom_id, cpf),
  CONSTRAINT fk_cu_cupom  FOREIGN KEY (cupom_id)  REFERENCES cupom(id)  ON DELETE RESTRICT,
  CONSTRAINT fk_cu_pedido FOREIGN KEY (pedido_id) REFERENCES pedido(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ingresso (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid             CHAR(36)        NOT NULL,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NOT NULL,
  pedido_id        BIGINT UNSIGNED NOT NULL,
  pedido_item_id   BIGINT UNSIGNED NOT NULL,
  lote_id          BIGINT UNSIGNED NOT NULL,
  ingresso_tipo_id BIGINT UNSIGNED NOT NULL,
  numero           INT UNSIGNED    NOT NULL COMMENT 'Sequencial por evento (exibido FF-<evento>-000123)',
  codigo_qr        CHAR(43)        NOT NULL COMMENT '256 bits base64url, assinado via HMAC na aplicação',
  valor_pago       DECIMAL(12,2)   NOT NULL COMMENT 'Preço - desconto deste ingresso',
  status           ENUM('valido','utilizado','cancelado','reembolsado','transferido') NOT NULL DEFAULT 'valido',
  dentro_do_evento TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Para reentrada: 1 após entrada, 0 após saída',
  primeiro_checkin_em DATETIME     NULL,
  ultimo_checkin_em DATETIME       NULL,
  total_checkins   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  cancelado_em     DATETIME        NULL,
  substituido_por  BIGINT UNSIGNED NULL COMMENT 'Ingresso gerado na transferência',
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ingresso_uuid (uuid),
  UNIQUE KEY uq_ingresso_qr (codigo_qr),
  UNIQUE KEY uq_ingresso_numero (evento_id, numero),
  KEY ix_ingresso_evento_status (evento_id, status),
  KEY ix_ingresso_pedido (pedido_id),
  KEY ix_ingresso_lote (lote_id),
  KEY ix_ingresso_org (organizador_id),
  CONSTRAINT fk_ingresso_organizador FOREIGN KEY (organizador_id)   REFERENCES organizador(id)   ON DELETE RESTRICT,
  CONSTRAINT fk_ingresso_evento      FOREIGN KEY (evento_id)        REFERENCES evento(id)        ON DELETE RESTRICT,
  CONSTRAINT fk_ingresso_pedido      FOREIGN KEY (pedido_id)        REFERENCES pedido(id)        ON DELETE RESTRICT,
  CONSTRAINT fk_ingresso_item        FOREIGN KEY (pedido_item_id)   REFERENCES pedido_item(id)   ON DELETE RESTRICT,
  CONSTRAINT fk_ingresso_lote        FOREIGN KEY (lote_id)          REFERENCES lote(id)          ON DELETE RESTRICT,
  CONSTRAINT fk_ingresso_tipo        FOREIGN KEY (ingresso_tipo_id) REFERENCES ingresso_tipo(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ingresso_subst       FOREIGN KEY (substituido_por)  REFERENCES ingresso(id)      ON DELETE RESTRICT,
  CONSTRAINT ck_ingresso_valor CHECK (valor_pago >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Participante: titular do ingresso. TODAS as colunas possíveis do formulário
-- existem aqui; o formulário do evento decide quais são pedidas/obrigatórias.
CREATE TABLE participante (
  id                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ingresso_id                 BIGINT UNSIGNED NOT NULL,
  organizador_id              BIGINT UNSIGNED NOT NULL,
  evento_id                   BIGINT UNSIGNED NOT NULL,
  -- identificação
  nome                        VARCHAR(150)    NOT NULL,
  nome_social                 VARCHAR(150)    NULL,
  cpf                         CHAR(11)        NULL,
  rg                          VARCHAR(20)     NULL,
  rg_orgao_uf                 VARCHAR(10)     NULL,
  passaporte                  VARCHAR(20)     NULL,
  data_nascimento             DATE            NULL,
  genero                      ENUM('feminino','masculino','nao_binario','outro','prefiro_nao_informar') NULL,
  nacionalidade               VARCHAR(60)     NULL,
  -- contato
  email                       VARCHAR(150)    NULL,
  telefone                    VARCHAR(20)     NULL,
  whatsapp                    VARCHAR(20)     NULL,
  instagram                   VARCHAR(60)     NULL,
  -- endereço
  cep                         CHAR(8)         NULL,
  logradouro                  VARCHAR(150)    NULL,
  numero                      VARCHAR(20)     NULL,
  complemento                 VARCHAR(60)     NULL,
  bairro                      VARCHAR(100)    NULL,
  cidade                      VARCHAR(100)    NULL,
  uf                          CHAR(2)         NULL,
  pais                        VARCHAR(60)     NULL,
  -- perfil
  empresa                     VARCHAR(150)    NULL,
  cargo                       VARCHAR(100)    NULL,
  escolaridade                VARCHAR(60)     NULL,
  tamanho_camiseta            ENUM('PP','P','M','G','GG','XG') NULL,
  -- evento
  meia_entrada_categoria      ENUM('estudante','idoso','pcd','jovem_baixa_renda','professor','outra') NULL,
  meia_entrada_documento      VARCHAR(60)     NULL,
  necessidade_acessibilidade  VARCHAR(255)    NULL COMMENT 'Dado sensível: só se o evento pedir',
  restricao_alimentar         VARCHAR(255)    NULL COMMENT 'Dado sensível: só se o evento pedir',
  contato_emergencia_nome     VARCHAR(150)    NULL,
  contato_emergencia_telefone VARCHAR(20)     NULL,
  como_conheceu               VARCHAR(100)    NULL,
  -- aceites (data/hora do aceite; NULL = não aceitou)
  aceite_termo_responsabilidade_em DATETIME   NULL,
  aceite_uso_imagem_em        DATETIME        NULL,
  aceite_regulamento_em       DATETIME        NULL,
  -- controle
  mesmo_que_comprador         TINYINT(1)      NOT NULL DEFAULT 0,
  anonimizado_em              DATETIME        NULL,
  criado_em                   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em               DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_participante_ingresso (ingresso_id),
  KEY ix_participante_evento_cpf (evento_id, cpf),
  KEY ix_participante_evento_nome (evento_id, nome),
  KEY ix_participante_org (organizador_id),
  CONSTRAINT fk_part_ingresso FOREIGN KEY (ingresso_id)    REFERENCES ingresso(id)    ON DELETE RESTRICT,
  CONSTRAINT fk_part_org      FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_part_evento   FOREIGN KEY (evento_id)      REFERENCES evento(id)      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE participante_resposta (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  participante_id     BIGINT UNSIGNED NOT NULL,
  formulario_campo_id BIGINT UNSIGNED NOT NULL,
  valor               TEXT            NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_resposta (participante_id, formulario_campo_id),
  CONSTRAINT fk_resp_part  FOREIGN KEY (participante_id)     REFERENCES participante(id)     ON DELETE RESTRICT,
  CONSTRAINT fk_resp_campo FOREIGN KEY (formulario_campo_id) REFERENCES formulario_campo(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ingresso_transferencia (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ingresso_origem_id  BIGINT UNSIGNED NOT NULL,
  ingresso_destino_id BIGINT UNSIGNED NOT NULL,
  de_nome          VARCHAR(150)    NOT NULL,
  para_nome        VARCHAR(150)    NOT NULL,
  para_email       VARCHAR(150)    NOT NULL,
  solicitado_por   ENUM('comprador','organizador') NOT NULL,
  ip               VARCHAR(45)     NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_transf_origem (ingresso_origem_id),
  CONSTRAINT fk_transf_origem  FOREIGN KEY (ingresso_origem_id)  REFERENCES ingresso(id) ON DELETE RESTRICT,
  CONSTRAINT fk_transf_destino FOREIGN KEY (ingresso_destino_id) REFERENCES ingresso(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MÓDULO 10 — CHECK-IN (QR Code e aplicativo, com modo offline)
-- =====================================================================
CREATE TABLE checkin_ponto (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NOT NULL,
  nome             VARCHAR(60)     NOT NULL COMMENT 'Ex.: Portão A, Camarote',
  ativo            TINYINT(1)      NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY ix_ponto_evento (evento_id),
  CONSTRAINT fk_ponto_org    FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ponto_evento FOREIGN KEY (evento_id)      REFERENCES evento(id)      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checkin_ponto_tipo (
  checkin_ponto_id BIGINT UNSIGNED NOT NULL,
  ingresso_tipo_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (checkin_ponto_id, ingresso_tipo_id),
  CONSTRAINT fk_cpt_ponto FOREIGN KEY (checkin_ponto_id) REFERENCES checkin_ponto(id) ON DELETE CASCADE,
  CONSTRAINT fk_cpt_tipo  FOREIGN KEY (ingresso_tipo_id) REFERENCES ingresso_tipo(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dispositivo_checkin (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NOT NULL,
  usuario_id       BIGINT UNSIGNED NOT NULL,
  nome             VARCHAR(80)     NOT NULL,
  plataforma       ENUM('android','ios','web') NOT NULL,
  token_hash       CHAR(64)        NOT NULL,
  ultimo_sync_em   DATETIME        NULL,
  revogado_em      DATETIME        NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_disp_token (token_hash),
  KEY ix_disp_evento (evento_id),
  CONSTRAINT fk_disp_org    FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_disp_evento FOREIGN KEY (evento_id)      REFERENCES evento(id)      ON DELETE RESTRICT,
  CONSTRAINT fk_disp_usu    FOREIGN KEY (usuario_id)     REFERENCES usuario(id)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checkin_log (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NOT NULL,
  ingresso_id      BIGINT UNSIGNED NULL COMMENT 'NULL quando o QR não existe',
  usuario_id       BIGINT UNSIGNED NOT NULL COMMENT 'Operador',
  checkin_ponto_id BIGINT UNSIGNED NULL,
  dispositivo_id   BIGINT UNSIGNED NULL,
  id_local_app     CHAR(36)        NULL COMMENT 'UUID gerado no app (idempotência do sync offline)',
  sentido          ENUM('entrada','saida') NOT NULL DEFAULT 'entrada',
  metodo           ENUM('qr_code','busca_manual','numero_ingresso') NOT NULL DEFAULT 'qr_code',
  codigo_lido      VARCHAR(100)    NULL,
  resultado        ENUM('aceito','ja_utilizado','cancelado','outro_evento','setor_nao_permitido',
                        'inexistente','assinatura_invalida','fora_do_horario') NOT NULL,
  offline          TINYINT(1)      NOT NULL DEFAULT 0,
  lido_em          DATETIME        NOT NULL COMMENT 'Hora da leitura no dispositivo (UTC)',
  sincronizado_em  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checkin_app (dispositivo_id, id_local_app),
  KEY ix_checkin_evento (evento_id, lido_em),
  KEY ix_checkin_ingresso (ingresso_id, lido_em),
  CONSTRAINT fk_ck_org     FOREIGN KEY (organizador_id)   REFERENCES organizador(id)          ON DELETE RESTRICT,
  CONSTRAINT fk_ck_evento  FOREIGN KEY (evento_id)        REFERENCES evento(id)               ON DELETE RESTRICT,
  CONSTRAINT fk_ck_ingresso FOREIGN KEY (ingresso_id)     REFERENCES ingresso(id)             ON DELETE RESTRICT,
  CONSTRAINT fk_ck_usuario FOREIGN KEY (usuario_id)       REFERENCES usuario(id)              ON DELETE RESTRICT,
  CONSTRAINT fk_ck_ponto   FOREIGN KEY (checkin_ponto_id) REFERENCES checkin_ponto(id)        ON DELETE RESTRICT,
  CONSTRAINT fk_ck_disp    FOREIGN KEY (dispositivo_id)   REFERENCES dispositivo_checkin(id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MÓDULO 11 — RECEBIMENTOS (pagamentos, reembolsos, avisos do gateway)
-- O dinheiro vai direto para a conta do organizador; aqui só se registra.
-- =====================================================================
CREATE TABLE pagamento (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id       BIGINT UNSIGNED NOT NULL,
  pedido_id            BIGINT UNSIGNED NOT NULL,
  conta_pagamento_id   BIGINT UNSIGNED NOT NULL,
  gateway_pagamento_id VARCHAR(100)    NULL,
  metodo               ENUM('pix','cartao','boleto') NOT NULL,
  parcelas             TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status               ENUM('criado','pendente','em_analise','aprovado','recusado','cancelado',
                            'expirado','estornado','estorno_parcial','contestado') NOT NULL DEFAULT 'criado',
  status_gateway       VARCHAR(60)     NULL COMMENT 'Status bruto do gateway',
  valor                DECIMAL(12,2)   NOT NULL,
  tarifa_gateway       DECIMAL(12,2)   NULL,
  comissao_retida      DECIMAL(12,2)   NULL COMMENT 'Comissão da plataforma retida no split',
  valor_liquido_org    DECIMAL(12,2)   NULL,
  pix_qr_code          TEXT            NULL,
  pix_expira_em        DATETIME        NULL,
  boleto_url           VARCHAR(255)    NULL,
  cartao_final         CHAR(4)         NULL,
  cartao_bandeira      VARCHAR(20)     NULL,
  motivo_recusa        VARCHAR(255)    NULL,
  aprovado_em          DATETIME        NULL,
  criado_em            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pagamento_gateway (conta_pagamento_id, gateway_pagamento_id),
  KEY ix_pagamento_pedido (pedido_id),
  KEY ix_pagamento_org_status (organizador_id, status, aprovado_em),
  CONSTRAINT fk_pag_org    FOREIGN KEY (organizador_id)     REFERENCES organizador(id)     ON DELETE RESTRICT,
  CONSTRAINT fk_pag_pedido FOREIGN KEY (pedido_id)          REFERENCES pedido(id)          ON DELETE RESTRICT,
  CONSTRAINT fk_pag_conta  FOREIGN KEY (conta_pagamento_id) REFERENCES conta_pagamento(id) ON DELETE RESTRICT,
  CONSTRAINT ck_pag_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reembolso (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id       BIGINT UNSIGNED NOT NULL,
  pedido_id            BIGINT UNSIGNED NOT NULL,
  pagamento_id         BIGINT UNSIGNED NULL,
  gateway_reembolso_id VARCHAR(100)    NULL,
  valor                DECIMAL(12,2)   NOT NULL,
  motivo               ENUM('arrependimento','evento_cancelado','evento_alterado','decisao_organizador',
                            'contestacao','pagamento_tardio_sem_estoque','outro') NOT NULL,
  observacao           VARCHAR(500)    NULL,
  solicitado_por       ENUM('comprador','organizador','sistema') NOT NULL,
  usuario_id           BIGINT UNSIGNED NULL,
  status               ENUM('solicitado','aprovado','processando','concluido','recusado','falhou') NOT NULL DEFAULT 'solicitado',
  concluido_em         DATETIME        NULL,
  criado_em            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_reembolso_pedido (pedido_id),
  KEY ix_reembolso_org (organizador_id, status),
  CONSTRAINT fk_reemb_org    FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_reemb_pedido FOREIGN KEY (pedido_id)      REFERENCES pedido(id)      ON DELETE RESTRICT,
  CONSTRAINT fk_reemb_pag    FOREIGN KEY (pagamento_id)   REFERENCES pagamento(id)   ON DELETE RESTRICT,
  CONSTRAINT fk_reemb_usu    FOREIGN KEY (usuario_id)     REFERENCES usuario(id)     ON DELETE RESTRICT,
  CONSTRAINT ck_reemb_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reembolso_ingresso (
  reembolso_id     BIGINT UNSIGNED NOT NULL,
  ingresso_id      BIGINT UNSIGNED NOT NULL,
  valor            DECIMAL(12,2)   NOT NULL,
  PRIMARY KEY (reembolso_id, ingresso_id),
  KEY ix_ri_ingresso (ingresso_id),
  CONSTRAINT fk_ri_reemb    FOREIGN KEY (reembolso_id) REFERENCES reembolso(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ri_ingresso FOREIGN KEY (ingresso_id)  REFERENCES ingresso(id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_evento (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  gateway_id         SMALLINT UNSIGNED NOT NULL,
  id_evento_externo  VARCHAR(150)    NOT NULL,
  tipo               VARCHAR(60)     NULL,
  conta_pagamento_id BIGINT UNSIGNED NULL,
  pagamento_id       BIGINT UNSIGNED NULL,
  cabecalhos         JSON            NOT NULL,
  corpo              JSON            NOT NULL,
  assinatura_valida  TINYINT(1)      NOT NULL DEFAULT 0,
  status             ENUM('recebido','processado','ignorado','erro') NOT NULL DEFAULT 'recebido',
  tentativas         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  erro               VARCHAR(500)    NULL,
  recebido_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processado_em      DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_webhook_externo (gateway_id, id_evento_externo),
  KEY ix_webhook_status (status, recebido_em),
  CONSTRAINT fk_wh_gateway FOREIGN KEY (gateway_id)         REFERENCES gateway(id)         ON DELETE RESTRICT,
  CONSTRAINT fk_wh_conta   FOREIGN KEY (conta_pagamento_id) REFERENCES conta_pagamento(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wh_pag     FOREIGN KEY (pagamento_id)       REFERENCES pagamento(id)       ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MÓDULO 12 — COMISSÃO DA PLATAFORMA E INADIMPLÊNCIA
-- =====================================================================
CREATE TABLE plano (
  id                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  nome                  VARCHAR(60)     NOT NULL,
  percentual            DECIMAL(5,2)    NOT NULL DEFAULT 0.00 COMMENT '% sobre o valor pago do ingresso',
  valor_fixo_ingresso   DECIMAL(12,2)   NOT NULL DEFAULT 0.00 COMMENT 'R$ somado por ingresso pago',
  valor_minimo_ingresso DECIMAL(12,2)   NOT NULL DEFAULT 0.00 COMMENT 'Taxa mínima por ingresso pago',
  valor_ingresso_gratuito DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa por ingresso gratuito/cortesia',
  modo_cobranca         ENUM('split','fatura') NOT NULL DEFAULT 'fatura',
  dia_fechamento        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  dias_vencimento       TINYINT UNSIGNED NOT NULL DEFAULT 10,
  publico               TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '0 = plano negociado',
  ativo                 TINYINT(1)      NOT NULL DEFAULT 1,
  criado_em             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plano_nome (nome),
  CONSTRAINT ck_plano_valores CHECK (percentual BETWEEN 0 AND 100 AND valor_fixo_ingresso >= 0
                                     AND valor_minimo_ingresso >= 0 AND valor_ingresso_gratuito >= 0),
  CONSTRAINT ck_plano_dias CHECK (dia_fechamento BETWEEN 1 AND 28 AND dias_vencimento BETWEEN 1 AND 60)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE organizador_plano (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  plano_id         INT UNSIGNED    NOT NULL,
  inicio           DATE            NOT NULL,
  fim              DATE            NULL COMMENT 'NULL = vigente',
  PRIMARY KEY (id),
  KEY ix_op_org (organizador_id, inicio),
  CONSTRAINT fk_op_organizador FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_op_plano       FOREIGN KEY (plano_id)       REFERENCES plano(id)       ON DELETE RESTRICT,
  CONSTRAINT ck_op_periodo CHECK (fim IS NULL OR fim >= inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fatura (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid             CHAR(36)        NOT NULL,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  competencia      CHAR(7)         NOT NULL COMMENT 'AAAA-MM',
  periodo_inicio   DATE            NOT NULL,
  periodo_fim      DATE            NOT NULL,
  qtd_ingressos    INT UNSIGNED    NOT NULL DEFAULT 0,
  base_calculo     DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  valor_cobrancas  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  valor_estornos   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  valor_total      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  vencimento       DATE            NULL,
  status           ENUM('aberta','fechada','paga','vencida','cancelada','isenta') NOT NULL DEFAULT 'aberta',
  boleto_url       VARCHAR(255)    NULL,
  pix_copia_cola   TEXT            NULL,
  fechada_em       DATETIME        NULL,
  paga_em          DATETIME        NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fatura_uuid (uuid),
  UNIQUE KEY uq_fatura_competencia (organizador_id, competencia),
  KEY ix_fatura_status_venc (status, vencimento),
  CONSTRAINT fk_fatura_organizador FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE taxa_lancamento (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NOT NULL,
  pedido_id        BIGINT UNSIGNED NOT NULL,
  ingresso_id      BIGINT UNSIGNED NOT NULL,
  plano_id         INT UNSIGNED    NOT NULL,
  fatura_id        BIGINT UNSIGNED NULL COMMENT 'Preenchido no fechamento (modo fatura)',
  tipo             ENUM('cobranca','estorno') NOT NULL,
  modo_cobranca    ENUM('split','fatura') NOT NULL,
  base             DECIMAL(12,2)   NOT NULL COMMENT 'Valor pago do ingresso',
  percentual       DECIMAL(5,2)    NOT NULL COMMENT 'Congelado na data do pagamento',
  valor_fixo       DECIMAL(12,2)   NOT NULL,
  valor            DECIMAL(12,2)   NOT NULL COMMENT 'Negativo no estorno',
  status           ENUM('pendente','retida_split','faturada','estornada') NOT NULL DEFAULT 'pendente',
  ocorrido_em      DATETIME        NOT NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_taxa_ingresso_tipo (ingresso_id, tipo),
  KEY ix_taxa_org_data (organizador_id, ocorrido_em),
  KEY ix_taxa_fatura (fatura_id),
  KEY ix_taxa_pendente (status, organizador_id),
  CONSTRAINT fk_taxa_organizador FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_taxa_evento      FOREIGN KEY (evento_id)      REFERENCES evento(id)      ON DELETE RESTRICT,
  CONSTRAINT fk_taxa_pedido      FOREIGN KEY (pedido_id)      REFERENCES pedido(id)      ON DELETE RESTRICT,
  CONSTRAINT fk_taxa_ingresso    FOREIGN KEY (ingresso_id)    REFERENCES ingresso(id)    ON DELETE RESTRICT,
  CONSTRAINT fk_taxa_plano       FOREIGN KEY (plano_id)       REFERENCES plano(id)       ON DELETE RESTRICT,
  CONSTRAINT fk_taxa_fatura      FOREIGN KEY (fatura_id)      REFERENCES fatura(id)      ON DELETE RESTRICT,
  CONSTRAINT ck_taxa_sinal CHECK ((tipo = 'cobranca' AND valor >= 0) OR (tipo = 'estorno' AND valor <= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fatura_pagamento (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fatura_id        BIGINT UNSIGNED NOT NULL,
  metodo           ENUM('pix','boleto','cartao','transferencia') NOT NULL,
  referencia       VARCHAR(100)    NULL,
  valor            DECIMAL(12,2)   NOT NULL,
  pago_em          DATETIME        NOT NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_fp_fatura (fatura_id),
  CONSTRAINT fk_fp_fatura FOREIGN KEY (fatura_id) REFERENCES fatura(id) ON DELETE RESTRICT,
  CONSTRAINT ck_fp_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MÓDULO 13 — MENSAGENS E DIVULGAÇÃO
-- =====================================================================
CREATE TABLE modelo_mensagem (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NULL COMMENT 'NULL = modelo padrão do sistema',
  evento_id        BIGINT UNSIGNED NULL COMMENT 'NULL = vale para todos os eventos do organizador',
  tipo             ENUM('confirmacao_compra','ingresso_emitido','pagamento_pendente','lembrete_evento',
                        'alteracao_evento','cancelamento_evento','reembolso','convite','transferencia',
                        'pos_evento') NOT NULL,
  canal            ENUM('email','whatsapp','sms') NOT NULL DEFAULT 'email',
  assunto          VARCHAR(150)    NULL,
  corpo_html       MEDIUMTEXT      NULL COMMENT 'Aceita {{participante_nome}}, {{evento_titulo}}...',
  corpo_texto      TEXT            NOT NULL,
  antecedencia_h   SMALLINT UNSIGNED NULL COMMENT 'Para lembrete_evento',
  ativo            TINYINT(1)      NOT NULL DEFAULT 1,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_modelo (organizador_id, evento_id, tipo, canal),
  CONSTRAINT fk_modelo_org    FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_modelo_evento FOREIGN KEY (evento_id)      REFERENCES evento(id)      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE consentimento_marketing (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  comprador_id     BIGINT UNSIGNED NOT NULL,
  canal            ENUM('email','whatsapp','sms') NOT NULL DEFAULT 'email',
  origem_pedido_id BIGINT UNSIGNED NULL,
  aceito_em        DATETIME        NOT NULL,
  revogado_em      DATETIME        NULL,
  ip               VARCHAR(45)     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_consent (organizador_id, comprador_id, canal),
  CONSTRAINT fk_cons_org    FOREIGN KEY (organizador_id)   REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cons_comp   FOREIGN KEY (comprador_id)     REFERENCES comprador(id)   ON DELETE RESTRICT,
  CONSTRAINT fk_cons_pedido FOREIGN KEY (origem_pedido_id) REFERENCES pedido(id)      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE campanha (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  evento_id        BIGINT UNSIGNED NULL COMMENT 'Evento divulgado',
  nome             VARCHAR(100)    NOT NULL,
  canal            ENUM('email','whatsapp','sms') NOT NULL DEFAULT 'email',
  assunto          VARCHAR(150)    NULL,
  corpo_html       MEDIUMTEXT      NULL,
  corpo_texto      TEXT            NOT NULL,
  segmento         JSON            NULL COMMENT 'Filtros: eventos anteriores, cidade, faixa etária...',
  status           ENUM('rascunho','agendada','enviando','concluida','cancelada') NOT NULL DEFAULT 'rascunho',
  agendada_para    DATETIME        NULL,
  total_destinatarios INT UNSIGNED NOT NULL DEFAULT 0,
  criado_por       BIGINT UNSIGNED NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_campanha_org (organizador_id, status),
  CONSTRAINT fk_camp_org    FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_camp_evento FOREIGN KEY (evento_id)      REFERENCES evento(id)      ON DELETE RESTRICT,
  CONSTRAINT fk_camp_usu    FOREIGN KEY (criado_por)     REFERENCES usuario(id)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fila única de envios (transacionais e campanhas)
CREATE TABLE mensagem_envio (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NULL,
  modelo_id        BIGINT UNSIGNED NULL,
  campanha_id      BIGINT UNSIGNED NULL,
  tipo             VARCHAR(40)     NOT NULL,
  canal            ENUM('email','whatsapp','sms') NOT NULL,
  destinatario     VARCHAR(150)    NOT NULL,
  comprador_id     BIGINT UNSIGNED NULL,
  pedido_id        BIGINT UNSIGNED NULL,
  ingresso_id      BIGINT UNSIGNED NULL,
  convidado_id     BIGINT UNSIGNED NULL,
  assunto          VARCHAR(150)    NULL,
  corpo_renderizado MEDIUMTEXT     NULL,
  status           ENUM('pendente','enviando','enviado','entregue','aberto','erro','descartado') NOT NULL DEFAULT 'pendente',
  prioridade       TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1 = transacional urgente',
  tentativas       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  provedor_msg_id  VARCHAR(150)    NULL,
  erro             VARCHAR(500)    NULL,
  agendado_para    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviado_em       DATETIME        NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_envio_campanha (campanha_id, comprador_id),
  KEY ix_envio_fila (status, prioridade, agendado_para),
  KEY ix_envio_pedido (pedido_id),
  CONSTRAINT fk_env_org      FOREIGN KEY (organizador_id) REFERENCES organizador(id)     ON DELETE RESTRICT,
  CONSTRAINT fk_env_modelo   FOREIGN KEY (modelo_id)      REFERENCES modelo_mensagem(id) ON DELETE RESTRICT,
  CONSTRAINT fk_env_campanha FOREIGN KEY (campanha_id)    REFERENCES campanha(id)        ON DELETE RESTRICT,
  CONSTRAINT fk_env_comp     FOREIGN KEY (comprador_id)   REFERENCES comprador(id)       ON DELETE RESTRICT,
  CONSTRAINT fk_env_pedido   FOREIGN KEY (pedido_id)      REFERENCES pedido(id)          ON DELETE RESTRICT,
  CONSTRAINT fk_env_ingresso FOREIGN KEY (ingresso_id)    REFERENCES ingresso(id)        ON DELETE RESTRICT,
  CONSTRAINT fk_env_conv     FOREIGN KEY (convidado_id)   REFERENCES convidado(id)       ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MÓDULO 14 — AUDITORIA
-- =====================================================================
CREATE TABLE auditoria (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NULL,
  usuario_id       BIGINT UNSIGNED NULL,
  acao             VARCHAR(60)     NOT NULL COMMENT 'ex.: evento.publicar, lote.alterar_preco, participante.exportar',
  tabela           VARCHAR(64)     NOT NULL,
  registro_id      BIGINT UNSIGNED NOT NULL,
  antes            JSON            NULL,
  depois           JSON            NULL,
  ip               VARCHAR(45)     NULL,
  ocorrido_em      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_aud_registro (tabela, registro_id),
  KEY ix_aud_org_data (organizador_id, ocorrido_em),
  KEY ix_aud_usuario (usuario_id, ocorrido_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- VIEWS DO DASHBOARD DO PRODUTOR
-- =====================================================================
CREATE SQL SECURITY INVOKER VIEW vw_dashboard_evento AS
SELECT e.organizador_id, e.id AS evento_id, e.titulo, e.status, e.data_inicio, e.capacidade,
       COALESCE(l.vendidos, 0)           AS ingressos_vendidos,
       COALESCE(l.reservados, 0)         AS ingressos_reservados,
       COALESCE(l.ofertados, 0)          AS ingressos_ofertados,
       COALESCE(p.pedidos_pagos, 0)      AS pedidos_pagos,
       COALESCE(p.receita_bruta, 0)      AS receita_bruta,
       COALESCE(p.descontos, 0)          AS descontos,
       COALESCE(i.checkins, 0)           AS checkins_realizados,
       COALESCE(t.comissao, 0)           AS comissao_plataforma
  FROM evento e
  LEFT JOIN (SELECT evento_id, SUM(vendido) vendidos, SUM(reservado) reservados, SUM(quantidade) ofertados
               FROM lote GROUP BY evento_id) l ON l.evento_id = e.id
  LEFT JOIN (SELECT evento_id, COUNT(*) pedidos_pagos, SUM(valor_total) receita_bruta, SUM(valor_desconto) descontos
               FROM pedido WHERE status IN ('pago','reembolso_parcial') GROUP BY evento_id) p ON p.evento_id = e.id
  LEFT JOIN (SELECT evento_id, COUNT(*) checkins
               FROM ingresso WHERE primeiro_checkin_em IS NOT NULL GROUP BY evento_id) i ON i.evento_id = e.id
  LEFT JOIN (SELECT evento_id, SUM(valor) comissao FROM taxa_lancamento GROUP BY evento_id) t ON t.evento_id = e.id;

CREATE SQL SECURITY INVOKER VIEW vw_vendas_lote AS
SELECT l.organizador_id, l.evento_id, it.nome AS tipo_ingresso, l.id AS lote_id, l.nome AS lote,
       l.modalidade, l.preco, l.quantidade, l.vendido, l.reservado,
       l.quantidade - l.vendido - l.reservado AS disponivel, l.status,
       ROUND(100 * l.vendido / l.quantidade, 1) AS pct_vendido
  FROM lote l JOIN ingresso_tipo it ON it.id = l.ingresso_tipo_id;

CREATE SQL SECURITY INVOKER VIEW vw_participantes AS
SELECT i.organizador_id, i.evento_id, i.numero AS numero_ingresso, i.uuid AS ingresso_uuid,
       pa.nome AS participante, pa.cpf AS participante_cpf, pa.email AS participante_email,
       it.nome AS tipo_ingresso, l.nome AS lote, l.modalidade, i.valor_pago, i.status AS status_ingresso,
       p.codigo AS numero_pedido, p.pago_em AS data_compra, p.origem, c.nome AS comprador, c.email AS comprador_email,
       cu.codigo AS cupom, pr.nome AS promoter,
       CASE WHEN i.primeiro_checkin_em IS NULL THEN 'nao' ELSE 'sim' END AS fez_checkin,
       i.primeiro_checkin_em
  FROM ingresso i
  JOIN pedido p          ON p.id  = i.pedido_id
  JOIN comprador c       ON c.id  = p.comprador_id
  JOIN lote l            ON l.id  = i.lote_id
  JOIN ingresso_tipo it  ON it.id = i.ingresso_tipo_id
  LEFT JOIN participante pa ON pa.ingresso_id = i.id
  LEFT JOIN cupom cu     ON cu.id = p.cupom_id
  LEFT JOIN promoter pr  ON pr.id = p.promoter_id;

-- =====================================================================
-- TRIGGERS — regras garantidas pelo banco
-- =====================================================================
DELIMITER $$

CREATE TRIGGER trg_evento_bi
BEFORE INSERT ON evento
FOR EACH ROW
BEGIN
  IF (SELECT situacao FROM organizador WHERE id = NEW.organizador_id) <> 'ativo' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Organizador nao esta ativo (debito em aberto ou suspenso): nao pode criar evento.';
  END IF;
  IF NEW.status <> 'rascunho' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Evento deve nascer como rascunho.';
  END IF;
END$$

CREATE TRIGGER trg_evento_bu
BEFORE UPDATE ON evento
FOR EACH ROW
BEGIN
  IF NEW.status = 'publicado' AND OLD.status = 'rascunho' THEN
    IF (SELECT situacao FROM organizador WHERE id = NEW.organizador_id) <> 'ativo' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Organizador nao esta ativo: nao pode publicar evento.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM termo_aceite ta JOIN termo t ON t.id = ta.termo_id
                    WHERE ta.organizador_id = NEW.organizador_id
                      AND t.tipo = 'responsabilidade_organizador' AND t.vigente = 1) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Termo de responsabilidade vigente nao aceito pelo organizador.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM lote WHERE evento_id = NEW.id) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Evento sem lote nao pode ser publicado.';
    END IF;
    IF EXISTS (SELECT 1 FROM lote WHERE evento_id = NEW.id AND preco > 0)
       AND NOT EXISTS (SELECT 1 FROM conta_pagamento
                        WHERE id = NEW.conta_pagamento_id AND organizador_id = NEW.organizador_id
                          AND status = 'ativa' AND ambiente = 'producao') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Evento com ingresso pago exige conta de recebimento ativa em producao.';
    END IF;
    IF NEW.data_inicio <= UTC_TIMESTAMP() THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Evento com data no passado nao pode ser publicado.';
    END IF;
    IF (SELECT COALESCE(SUM(quantidade),0) FROM lote WHERE evento_id = NEW.id) > NEW.capacidade THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Soma dos lotes ultrapassa a capacidade do evento.';
    END IF;
    SET NEW.publicado_em = UTC_TIMESTAMP();
  END IF;
  IF NEW.status = 'cancelado' AND OLD.status <> 'cancelado' THEN
    SET NEW.cancelado_em = UTC_TIMESTAMP();
  END IF;
END$$

CREATE TRIGGER trg_lote_bu
BEFORE UPDATE ON lote
FOR EACH ROW
BEGIN
  IF OLD.vendido + OLD.reservado > 0 AND NEW.preco <> OLD.preco THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Lote com vendas nao pode ter o preco alterado: crie um novo lote.';
  END IF;
  IF NEW.reservado + NEW.vendido = NEW.quantidade AND NEW.status = 'ativo' THEN
    SET NEW.status = 'esgotado';
  END IF;
END$$

CREATE TRIGGER trg_conta_bu
BEFORE UPDATE ON conta_pagamento
FOR EACH ROW
BEGIN
  IF NEW.status = 'homologada' AND OLD.status <> 'homologada' THEN
    IF (SELECT COUNT(DISTINCT teste) FROM homologacao_teste
         WHERE conta_pagamento_id = NEW.id AND resultado = 'ok'
           AND teste IN ('conexao_oauth','pix_aprovado','cartao_aprovado','webhook_recebido','reembolso')) < 5 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Homologacao incompleta: faltam testes obrigatorios com resultado ok.';
    END IF;
    SET NEW.homologada_em = UTC_TIMESTAMP();
  END IF;
  IF NEW.status = 'ativa' AND OLD.status <> 'ativa' THEN
    IF NEW.homologada_em IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conta so pode ser ativada depois de homologada.';
    END IF;
    SET NEW.ativada_em = UTC_TIMESTAMP();
  END IF;
END$$

DELIMITER ;

-- =====================================================================
-- DADOS INICIAIS
-- =====================================================================
INSERT INTO parametro_sistema (chave, valor, descricao) VALUES
 ('reserva_minutos', '15', 'Tempo que o estoque fica reservado no checkout'),
 ('arrependimento_dias', '7', 'Prazo de arrependimento (art. 49 CDC)'),
 ('fuso_exibicao', 'America/Sao_Paulo', 'Fuso para exibir datas');

INSERT INTO gateway (codigo, nome, suporta_split, suporta_pix, suporta_cartao, suporta_boleto, ativo) VALUES
 ('mercadopago', 'Mercado Pago', 1, 1, 1, 1, 1),
 ('paypal',      'PayPal',       0, 0, 1, 0, 0),
 ('pagseguro',   'PagBank',      0, 1, 1, 1, 0);

INSERT INTO evento_categoria (nome, slug) VALUES
 ('Shows e música','shows'),('Festas','festas'),('Bares e gastronomia','gastronomia'),
 ('Teatro e cultura','cultura'),('Esportes','esportes'),('Cursos e workshops','cursos'),
 ('Congressos e palestras','congressos'),('Infantil','infantil'),('Religioso','religioso'),('Online','online');

INSERT INTO plano (nome, percentual, valor_fixo_ingresso, valor_minimo_ingresso, modo_cobranca) VALUES
 ('Lançamento', 0.00, 0.00, 0.00, 'fatura');

INSERT INTO permissao (codigo, modulo, descricao) VALUES
 ('dashboard.ver','dashboard','Ver o painel com indicadores'),
 ('organizador.editar','organizador','Editar dados e página do organizador'),
 ('evento.ver','evento','Ver eventos'),
 ('evento.criar','evento','Criar e editar eventos em rascunho'),
 ('evento.publicar','evento','Publicar, pausar e encerrar eventos'),
 ('evento.cancelar','evento','Cancelar evento'),
 ('lote.gerenciar','evento','Criar e editar tipos de ingresso e lotes'),
 ('formulario.gerenciar','evento','Configurar o formulário de inscrição'),
 ('mensagem.gerenciar','divulgacao','Editar mensagens e e-mail de confirmação'),
 ('cupom.gerenciar','divulgacao','Criar e editar cupons'),
 ('promoter.gerenciar','divulgacao','Criar links de promoters'),
 ('promoter.ver_proprias','divulgacao','Promoter vê só as próprias vendas'),
 ('campanha.gerenciar','divulgacao','Criar e enviar campanhas'),
 ('convidado.gerenciar','convidados','Gerenciar listas e convites'),
 ('acesso_privado.gerenciar','convidados','Criar links de acesso privado'),
 ('pedido.ver','vendas','Ver pedidos e compradores'),
 ('pedido.emitir_manual','vendas','Emitir ingresso manual/cortesia'),
 ('pedido.reembolsar','vendas','Aprovar reembolsos e cancelamentos'),
 ('participante.ver','vendas','Ver lista de participantes'),
 ('participante.ver_dados_completos','vendas','Ver CPF, telefone e endereço completos'),
 ('participante.exportar','vendas','Exportar participantes (CSV/Excel)'),
 ('participante.editar','vendas','Corrigir dados e transferir titularidade'),
 ('checkin.realizar','checkin','Fazer check-in (QR Code e busca)'),
 ('checkin.acompanhar','checkin','Acompanhar entradas e lotação em tempo real'),
 ('checkin.configurar','checkin','Configurar portões e dispositivos'),
 ('financeiro.ver','financeiro','Ver recebimentos, faturas e comissões'),
 ('financeiro.contas','financeiro','Cadastrar e homologar contas de recebimento'),
 ('financeiro.faturas','financeiro','Pagar faturas da plataforma'),
 ('equipe.gerenciar','equipe','Convidar usuários e definir perfis'),
 ('auditoria.ver','equipe','Ver o histórico de ações');

INSERT INTO perfil_acesso (organizador_id, codigo, nome, descricao, editavel) VALUES
 (NULL,'dono','Dono','Acesso total; responde pelo organizador',0),
 (NULL,'gerente','Gerente','Opera eventos, vendas e divulgação; sem financeiro e equipe',1),
 (NULL,'financeiro','Financeiro','Recebimentos, reembolsos, faturas e contas',1),
 (NULL,'checkin','Check-in (portaria)','Só faz check-in no dia do evento',1),
 (NULL,'seguranca','Segurança','Acompanha entradas e lotação; não vê dados financeiros',1),
 (NULL,'promoter','Promoter','Vê só as próprias vendas e links',1);

INSERT INTO perfil_permissao (perfil_id, permissao_id)
SELECT pa.id, p.id FROM perfil_acesso pa JOIN permissao p
 WHERE pa.organizador_id IS NULL AND (
   pa.codigo = 'dono'
   OR (pa.codigo = 'gerente' AND p.codigo IN ('dashboard.ver','evento.ver','evento.criar','evento.publicar',
        'lote.gerenciar','formulario.gerenciar','mensagem.gerenciar','cupom.gerenciar','promoter.gerenciar',
        'campanha.gerenciar','convidado.gerenciar','acesso_privado.gerenciar','pedido.ver','pedido.emitir_manual',
        'participante.ver','participante.ver_dados_completos','participante.exportar','participante.editar',
        'checkin.realizar','checkin.acompanhar','checkin.configurar'))
   OR (pa.codigo = 'financeiro' AND p.codigo IN ('dashboard.ver','evento.ver','pedido.ver','pedido.reembolsar',
        'participante.ver','financeiro.ver','financeiro.contas','financeiro.faturas'))
   OR (pa.codigo = 'checkin' AND p.codigo IN ('checkin.realizar'))
   OR (pa.codigo = 'seguranca' AND p.codigo IN ('evento.ver','checkin.acompanhar','participante.ver'))
   OR (pa.codigo = 'promoter' AND p.codigo IN ('promoter.ver_proprias'))
 );

INSERT INTO campo_catalogo (codigo, rotulo_padrao, tipo, grupo, opcoes, sensivel, sempre_obrigatorio, ordem) VALUES
 ('nome','Nome completo','texto','identificacao',NULL,0,1,1),
 ('nome_social','Nome social','texto','identificacao',NULL,0,0,2),
 ('cpf','CPF','cpf','identificacao',NULL,0,0,3),
 ('rg','RG','documento','identificacao',NULL,0,0,4),
 ('rg_orgao_uf','Órgão emissor/UF','texto','identificacao',NULL,0,0,5),
 ('passaporte','Passaporte (estrangeiros)','documento','identificacao',NULL,0,0,6),
 ('data_nascimento','Data de nascimento','data','identificacao',NULL,0,0,7),
 ('genero','Gênero','selecao','identificacao','["feminino","masculino","nao_binario","outro","prefiro_nao_informar"]',0,0,8),
 ('nacionalidade','Nacionalidade','texto','identificacao',NULL,0,0,9),
 ('email','E-mail','email','contato',NULL,0,0,10),
 ('telefone','Telefone','telefone','contato',NULL,0,0,11),
 ('whatsapp','WhatsApp','telefone','contato',NULL,0,0,12),
 ('instagram','Instagram','texto','contato',NULL,0,0,13),
 ('cep','CEP','cep','endereco',NULL,0,0,20),
 ('logradouro','Endereço','texto','endereco',NULL,0,0,21),
 ('numero','Número','texto','endereco',NULL,0,0,22),
 ('complemento','Complemento','texto','endereco',NULL,0,0,23),
 ('bairro','Bairro','texto','endereco',NULL,0,0,24),
 ('cidade','Cidade','texto','endereco',NULL,0,0,25),
 ('uf','Estado','uf','endereco',NULL,0,0,26),
 ('pais','País','texto','endereco',NULL,0,0,27),
 ('empresa','Empresa','texto','perfil',NULL,0,0,30),
 ('cargo','Cargo','texto','perfil',NULL,0,0,31),
 ('escolaridade','Escolaridade','texto','perfil',NULL,0,0,32),
 ('tamanho_camiseta','Tamanho da camiseta','selecao','perfil','["PP","P","M","G","GG","XG"]',0,0,33),
 ('meia_entrada_categoria','Categoria da meia-entrada','selecao','evento','["estudante","idoso","pcd","jovem_baixa_renda","professor","outra"]',0,0,40),
 ('meia_entrada_documento','Documento da meia-entrada','documento','evento',NULL,0,0,41),
 ('necessidade_acessibilidade','Necessidade de acessibilidade','texto','evento',NULL,1,0,42),
 ('restricao_alimentar','Restrição alimentar','texto','evento',NULL,1,0,43),
 ('contato_emergencia_nome','Contato de emergência','texto','evento',NULL,0,0,44),
 ('contato_emergencia_telefone','Telefone de emergência','telefone','evento',NULL,0,0,45),
 ('como_conheceu','Como conheceu o evento','texto','evento',NULL,0,0,46),
 ('aceite_termo_responsabilidade_em','Li e aceito o termo de responsabilidade','aceite','aceites',NULL,0,0,50),
 ('aceite_uso_imagem_em','Autorizo o uso da minha imagem','aceite','aceites',NULL,0,0,51),
 ('aceite_regulamento_em','Li e aceito o regulamento','aceite','aceites',NULL,0,0,52);

INSERT INTO modelo_mensagem (organizador_id, evento_id, tipo, canal, assunto, corpo_texto) VALUES
 (NULL, NULL, 'confirmacao_compra', 'email', 'Pedido {{pedido_codigo}} confirmado — {{evento_titulo}}',
  'Olá, {{comprador_nome}}! Seu pedido {{pedido_codigo}} para {{evento_titulo}} em {{evento_data}} foi confirmado. Seus ingressos estão em anexo e em {{link_meus_ingressos}}. Evento organizado por {{organizador_razao_social}} ({{organizador_documento}}), responsável pelo evento. Dúvidas: {{organizador_email_contato}}.'),
 (NULL, NULL, 'lembrete_evento', 'email', 'Falta pouco: {{evento_titulo}}',
  'Olá, {{participante_nome}}! {{evento_titulo}} começa em {{evento_data}}, em {{local_nome}}. Apresente o QR Code do ingresso nº {{ingresso_numero}} na entrada.');
