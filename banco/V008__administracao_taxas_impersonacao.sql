-- =====================================================================
-- Fora da Fila — V008: Administração global — motor de taxas e "assumir conta"
-- (1) taxa_regra: taxas padrão da plataforma, com faixas por volume (tiered pricing)
-- (2) taxa_organizador: taxas e isenções específicas de uma empresa, com validade
-- (3) impersonacao_log: cada vez que um administrador assume a conta de uma empresa
-- (4) auditoria.admin_impersonando_id: ações feitas pelo administrador dentro da empresa
--
-- Idempotente (IF NOT EXISTS). Não consulta information_schema.
-- Importar no phpMyAdmin com o banco u839930205_foradafila selecionado.
-- =====================================================================

CREATE TABLE IF NOT EXISTS taxa_regra (
  id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  nome             VARCHAR(80)     NOT NULL,
  operacao         ENUM('ingresso_pago','ingresso_gratuito','pix','cartao','boleto') NOT NULL DEFAULT 'ingresso_pago'
                   COMMENT 'Sobre o que a taxa incide',
  percentual       DECIMAL(6,3)    NOT NULL DEFAULT 0.000 COMMENT '% sobre o valor',
  valor_fixo       DECIMAL(12,2)   NOT NULL DEFAULT 0.00  COMMENT 'R$ por operação',
  valor_minimo     DECIMAL(12,2)   NOT NULL DEFAULT 0.00  COMMENT 'Taxa mínima por operação',
  volume_min       DECIMAL(14,2)   NOT NULL DEFAULT 0.00  COMMENT 'Faixa: volume movimentado no mês a partir de',
  volume_max       DECIMAL(14,2)   NULL                   COMMENT 'Faixa: até (NULL = sem limite)',
  carencia_dias    SMALLINT UNSIGNED NOT NULL DEFAULT 0   COMMENT 'Dias de isenção para contas novas',
  ativo            TINYINT(1)      NOT NULL DEFAULT 1,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_taxa_regra_operacao (operacao, ativo, volume_min),
  CONSTRAINT ck_taxa_regra CHECK (percentual BETWEEN 0 AND 100 AND valor_fixo >= 0 AND valor_minimo >= 0
                                  AND volume_min >= 0 AND (volume_max IS NULL OR volume_max > volume_min))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS taxa_organizador (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizador_id   BIGINT UNSIGNED NOT NULL,
  operacao         ENUM('todas','ingresso_pago','ingresso_gratuito','pix','cartao','boleto') NOT NULL DEFAULT 'todas',
  isento           TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = isenção total no período',
  percentual       DECIMAL(6,3)    NULL COMMENT 'NULL = usa a regra padrão',
  valor_fixo       DECIMAL(12,2)   NULL COMMENT 'NULL = usa a regra padrão',
  valor_minimo     DECIMAL(12,2)   NULL COMMENT 'NULL = usa a regra padrão',
  inicio           DATE            NOT NULL,
  fim              DATE            NULL COMMENT 'NULL = sem data de fim',
  observacao       VARCHAR(255)    NULL,
  criado_por       BIGINT UNSIGNED NULL,
  criado_em        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_taxa_org (organizador_id, operacao, inicio),
  CONSTRAINT fk_taxa_org_organizador FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT,
  CONSTRAINT fk_taxa_org_usuario     FOREIGN KEY (criado_por)     REFERENCES usuario(id)     ON DELETE RESTRICT,
  CONSTRAINT ck_taxa_org CHECK ((percentual IS NULL OR percentual BETWEEN 0 AND 100) AND (valor_fixo IS NULL OR valor_fixo >= 0)
                                AND (valor_minimo IS NULL OR valor_minimo >= 0) AND (fim IS NULL OR fim >= inicio))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS impersonacao_log (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id         BIGINT UNSIGNED NOT NULL COMMENT 'Administrador da plataforma que assumiu a conta',
  organizador_id   BIGINT UNSIGNED NOT NULL,
  motivo           VARCHAR(255)    NULL,
  inicio           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fim              DATETIME        NULL,
  ip               VARCHAR(45)     NULL,
  user_agent       VARCHAR(255)    NULL,
  acoes            INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Ações registradas em auditoria durante a sessão',
  PRIMARY KEY (id),
  KEY ix_imp_org (organizador_id, inicio),
  KEY ix_imp_admin (admin_id, inicio),
  CONSTRAINT fk_imp_admin FOREIGN KEY (admin_id)       REFERENCES usuario(id)     ON DELETE RESTRICT,
  CONSTRAINT fk_imp_org   FOREIGN KEY (organizador_id) REFERENCES organizador(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE auditoria
  ADD COLUMN IF NOT EXISTS admin_impersonando_id BIGINT UNSIGNED NULL
      COMMENT 'Administrador da plataforma que fez a ação dentro da conta da empresa' AFTER usuario_id,
  ADD COLUMN IF NOT EXISTS impersonacao_id BIGINT UNSIGNED NULL AFTER admin_impersonando_id;
ALTER TABLE auditoria
  ADD KEY IF NOT EXISTS ix_auditoria_impersonacao (impersonacao_id);

-- Regra padrão inicial (plano de lançamento: sem taxa). Só insere se a tabela estiver vazia.
INSERT INTO taxa_regra (nome, operacao, percentual, valor_fixo, valor_minimo, volume_min, volume_max, carencia_dias)
SELECT 'Lançamento — ingresso pago', 'ingresso_pago', 0.000, 0.00, 0.00, 0.00, NULL, 0
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM taxa_regra);

-- Conferência (deve mostrar "V008 ok")
SELECT IF(COUNT(*) >= 1, 'V008 ok', 'V008 FALHOU') AS resultado FROM taxa_regra;
SELECT COUNT(*) AS taxas_por_empresa FROM taxa_organizador;
SELECT COUNT(*) AS impersonacoes FROM impersonacao_log;
SELECT id, admin_impersonando_id, impersonacao_id FROM auditoria ORDER BY id DESC LIMIT 1;
