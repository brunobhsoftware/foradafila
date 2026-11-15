-- Fora da Fila — V020: Beneficiário da comissão nas taxas específicas de empresa

ALTER TABLE `taxa_organizador`
    ADD COLUMN IF NOT EXISTS `beneficiario` ENUM('plataforma','empresa') NOT NULL DEFAULT 'plataforma'
        AFTER `isento`;
