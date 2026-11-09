-- V017 — Fora da Fila (09/11/2026) — Opções de marketing
-- 1) smtp_conta: até 5 contas SMTP da própria empresa (senha criptografada pela aplicação com a APP_KEY).
-- 2) lista_email / lista_email_contato: listas de e-mail da empresa (manual, CSV ou compradores/participantes de eventos).
-- 3) email_descadastro: quem pediu para não receber mais e-mails de divulgação da empresa (vale para todas as listas).
-- Idempotente: pode rodar mais de uma vez.

CREATE TABLE IF NOT EXISTS smtp_conta (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid             CHAR(36)        NOT NULL,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  nome             VARCHAR(60)     NOT NULL COMMENT 'Rótulo: Divulgação, Newsletter...',
  remetente_email  VARCHAR(150)    NOT NULL,
  remetente_nome   VARCHAR(100)    NOT NULL,
  responder_para   VARCHAR(150)    NULL,
  host             VARCHAR(150)    NOT NULL,
  porta            SMALLINT UNSIGNED NOT NULL DEFAULT 465,
  seguranca        ENUM('ssl','tls','nenhuma') NOT NULL DEFAULT 'ssl',
  usuario          VARCHAR(150)    NOT NULL,
  senha_cripto     TEXT            NULL,
  limite_hora      INT UNSIGNED    NOT NULL DEFAULT 100 COMMENT 'Máximo de e-mails por hora nesta conta',
  ativo            TINYINT(1)      NOT NULL DEFAULT 1,
  testado_em       DATETIME        NULL,
  ultimo_erro      VARCHAR(500)    NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_smtp_uuid (uuid),
  KEY ix_smtp_org (organizador_id),
  CONSTRAINT fk_smtp_org FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lista_email (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid             CHAR(36)        NOT NULL,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  nome             VARCHAR(100)    NOT NULL,
  descricao        VARCHAR(255)    NULL,
  criado_por       BIGINT UNSIGNED NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lista_email_uuid (uuid),
  KEY ix_lista_email_org (organizador_id),
  CONSTRAINT fk_lista_email_org FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lista_email_contato (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lista_id         BIGINT UNSIGNED NOT NULL,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  email            VARCHAR(150)    NOT NULL,
  nome             VARCHAR(150)    NULL,
  telefone         VARCHAR(20)     NULL,
  origem           VARCHAR(40)     NOT NULL DEFAULT 'manual' COMMENT 'manual, csv, evento:<id>',
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lista_contato (lista_id, email),
  KEY ix_lista_contato_org (organizador_id, email),
  CONSTRAINT fk_lec_lista FOREIGN KEY (lista_id) REFERENCES lista_email(id) ON DELETE CASCADE,
  CONSTRAINT fk_lec_org FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_descadastro (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  email            VARCHAR(150)    NOT NULL,
  motivo           VARCHAR(255)    NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_descadastro (organizador_id, email),
  CONSTRAINT fk_desc_org FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'V017 ok' AS resultado;
