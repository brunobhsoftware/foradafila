-- V019: Cupons de cadastro — promoções vinculadas ao registro de novos produtores.
-- O admin cria cupons com uma configuração de taxa (percentual, fixo, mínimo, isenção),
-- duração do benefício em meses (NULL = permanente) e limite de usos (NULL = ilimitado).
-- Ao se cadastrar com um código válido, o produtor recebe uma `taxa_organizador` automática.
-- Idempotente.

USE u839930205_foradafila;

CREATE TABLE IF NOT EXISTS cupom_cadastro (
    id          BIGINT       NOT NULL AUTO_INCREMENT,
    uuid        CHAR(36)     NOT NULL,
    codigo      VARCHAR(50)  NOT NULL,
    nome        VARCHAR(150) NOT NULL,
    -- taxa aplicada ao novo organizador
    isento      TINYINT(1)   NOT NULL DEFAULT 0,          -- 1 = isento total
    percentual  DECIMAL(5,2)          DEFAULT NULL,       -- NULL = herda padrão
    valor_fixo  DECIMAL(12,2)         DEFAULT NULL,
    valor_minimo DECIMAL(12,2)        DEFAULT NULL,
    -- duração do benefício (NULL = sem validade)
    meses_validade INT               DEFAULT NULL,
    -- limite de usos (NULL = ilimitado)
    limite_usos INT                  DEFAULT NULL,
    usos        INT          NOT NULL DEFAULT 0,
    observacao  VARCHAR(255)          DEFAULT NULL,
    ativo       TINYINT(1)   NOT NULL DEFAULT 1,
    criado_por  BIGINT                DEFAULT NULL,
    criado_em   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cupom_cadastro_uuid   (uuid),
    UNIQUE KEY uk_cupom_cadastro_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rastrear qual cupom foi usado no cadastro de cada organização
ALTER TABLE organizador
    ADD COLUMN IF NOT EXISTS cupom_cadastro_id BIGINT DEFAULT NULL AFTER situacao_motivo;
