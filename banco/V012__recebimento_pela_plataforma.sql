-- =====================================================================
-- Fora da Fila — V012: recebimento pela plataforma e repasses; aplicação Mercado Pago própria da empresa
-- Enquanto a conexão direta da conta do Mercado Pago do produtor não é liberada, as vendas pagas caem na conta
-- da BH Software (aplicação do Fora da Fila), que retém a comissão e repassa o restante ao produtor por Pix ou TED.
-- Idempotente. Não consulta information_schema.
-- =====================================================================

ALTER TABLE organizador
  ADD COLUMN IF NOT EXISTS pix_tipo ENUM('cpf','cnpj','email','telefone','aleatoria') NULL
      COMMENT 'Tipo da chave Pix para repasses',
  ADD COLUMN IF NOT EXISTS pix_chave VARCHAR(140) NULL COMMENT 'Chave Pix para repasses',
  ADD COLUMN IF NOT EXISTS recebimento_preferido ENUM('pix','conta') NULL COMMENT 'Como prefere receber os repasses',
  ADD COLUMN IF NOT EXISTS recebimento_atualizado_em DATETIME NULL;

ALTER TABLE organizador
  ADD COLUMN IF NOT EXISTS recebimento_modo ENUM('plataforma','propria') NOT NULL DEFAULT 'plataforma'
      COMMENT 'plataforma = vendas na conta da BH Software com repasse; propria = aplicação Mercado Pago da própria empresa';

ALTER TABLE conta_pagamento
  ADD COLUMN IF NOT EXISTS modo ENUM('direta','plataforma','propria') NOT NULL DEFAULT 'direta'
      COMMENT 'direta = conectada por OAuth; plataforma = conta da BH Software com repasse; propria = aplicação da própria empresa',
  ADD COLUMN IF NOT EXISTS client_id VARCHAR(100) NULL COMMENT 'Aplicação própria: Client ID de produção',
  ADD COLUMN IF NOT EXISTS client_secret_cript VARBINARY(1024) NULL,
  ADD COLUMN IF NOT EXISTS webhook_secret_cript VARBINARY(1024) NULL,
  ADD COLUMN IF NOT EXISTS teste_client_id VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS teste_client_secret_cript VARBINARY(1024) NULL,
  ADD COLUMN IF NOT EXISTS teste_access_token_cript VARBINARY(2048) NULL,
  ADD COLUMN IF NOT EXISTS teste_public_key VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS teste_webhook_secret_cript VARBINARY(1024) NULL;

ALTER TABLE conta_pagamento
  MODIFY COLUMN modo ENUM('direta','plataforma','propria') NOT NULL DEFAULT 'direta'
      COMMENT 'direta = conectada por OAuth; plataforma = conta da BH Software com repasse; propria = aplicação da própria empresa';

CREATE TABLE IF NOT EXISTS repasse (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  organizador_id BIGINT UNSIGNED NOT NULL,
  valor_vendas DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Soma dos pagamentos aprovados incluídos',
  valor_comissao DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa da plataforma retida',
  valor_tarifas DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Tarifas da operadora',
  valor_reembolsos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  valor_repasse DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor transferido ao produtor',
  status ENUM('pendente','pago','cancelado') NOT NULL DEFAULT 'pendente',
  destino VARCHAR(255) NULL COMMENT 'Pix ou conta usados (cópia do cadastro no momento do repasse)',
  previsto_para DATE NULL,
  pago_em DATETIME NULL,
  comprovante VARCHAR(255) NULL COMMENT 'Identificador da transferência (E2E do Pix, nº do TED)',
  observacao VARCHAR(500) NULL,
  criado_por BIGINT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_repasse_uuid (uuid),
  KEY ix_repasse_org (organizador_id, status),
  CONSTRAINT fk_repasse_org FOREIGN KEY (organizador_id) REFERENCES organizador (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pagamento
  ADD COLUMN IF NOT EXISTS repasse_id BIGINT UNSIGNED NULL COMMENT 'Repasse em que este pagamento foi acertado com o produtor';

SELECT 'V012 ok' AS resultado;
