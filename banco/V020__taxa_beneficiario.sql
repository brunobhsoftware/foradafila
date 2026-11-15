-- V020: quem fica com a comissão da taxa específica de empresa
-- `beneficiario = plataforma` (padrão): BH Software retém a comissão.
-- `beneficiario = empresa`: a empresa fica com tudo — a plataforma não cobra nada nesse período.
-- Idempotente.

ALTER TABLE taxa_organizador
    ADD COLUMN IF NOT EXISTS beneficiario ENUM('plataforma','empresa') NOT NULL DEFAULT 'plataforma'
        COMMENT 'Quem retém a comissão: plataforma (BH) ou empresa (isenção de cobrança)'
        AFTER isento;
