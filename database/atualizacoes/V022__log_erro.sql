-- V022: Tabela de log de erros da plataforma (Administração → Log de erros)
-- Idempotente

CREATE TABLE IF NOT EXISTS log_erro (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organizador_id BIGINT    NULL,
    contexto    VARCHAR(100) NOT NULL COMMENT 'Onde aconteceu (ex.: checkout.criarOrder, webhook.processar)',
    mensagem    TEXT         NOT NULL,
    detalhes    TEXT         NULL     COMMENT 'Stack trace resumido ou dados extras (JSON)',
    url         VARCHAR(500) NULL,
    ip          VARCHAR(45)  NULL,
    usuario_id  BIGINT       NULL,
    comprador_id BIGINT      NULL,
    criado_em   DATETIME     NOT NULL DEFAULT UTC_TIMESTAMP(),
    INDEX idx_log_erro_criado (criado_em),
    INDEX idx_log_erro_contexto (contexto),
    INDEX idx_log_erro_org (organizador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
