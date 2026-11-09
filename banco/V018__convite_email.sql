-- V018 — Fora da Fila (09/11/2026) — Convite por e-mail (campanhas)
-- campanha ganha uuid, lista, conta SMTP e contadores; campanha_envio guarda cada destinatário (fila do cron, um envio por e-mail).
-- lista_email_contato ganha o registro da declaração de consentimento.
-- Idempotente: pode rodar mais de uma vez.

ALTER TABLE campanha ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL AFTER id;
ALTER TABLE campanha ADD COLUMN IF NOT EXISTS lista_id BIGINT UNSIGNED NULL AFTER evento_id;
ALTER TABLE campanha ADD COLUMN IF NOT EXISTS smtp_conta_id BIGINT UNSIGNED NULL AFTER lista_id;
ALTER TABLE campanha ADD COLUMN IF NOT EXISTS enviados INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_destinatarios;
ALTER TABLE campanha ADD COLUMN IF NOT EXISTS erros INT UNSIGNED NOT NULL DEFAULT 0 AFTER enviados;
ALTER TABLE campanha ADD COLUMN IF NOT EXISTS iniciada_em DATETIME NULL AFTER agendada_para;
ALTER TABLE campanha ADD COLUMN IF NOT EXISTS concluida_em DATETIME NULL AFTER iniciada_em;

CREATE TABLE IF NOT EXISTS campanha_envio (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campanha_id      BIGINT UNSIGNED NOT NULL,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  email            VARCHAR(150)    NOT NULL,
  nome             VARCHAR(150)    NULL,
  status           ENUM('pendente','enviado','erro','pulado') NOT NULL DEFAULT 'pendente',
  erro             VARCHAR(500)    NULL,
  smtp_conta_id    BIGINT UNSIGNED NULL,
  enviado_em       DATETIME        NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campanha_email (campanha_id, email),
  KEY ix_ce_fila (status, campanha_id),
  KEY ix_ce_smtp_hora (smtp_conta_id, enviado_em),
  CONSTRAINT fk_ce_campanha FOREIGN KEY (campanha_id) REFERENCES campanha(id) ON DELETE CASCADE,
  CONSTRAINT fk_ce_org FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE lista_email_contato ADD COLUMN IF NOT EXISTS consentimento_em DATETIME NULL AFTER origem;
ALTER TABLE lista_email_contato ADD COLUMN IF NOT EXISTS consentimento_por BIGINT UNSIGNED NULL AFTER consentimento_em;

SELECT 'V018 ok' AS resultado;
