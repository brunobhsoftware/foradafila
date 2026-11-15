-- V021: escopos de VARCHAR(255) para TEXT (o Mercado Pago retorna muitos escopos).
-- Idempotente.

ALTER TABLE conta_pagamento
  MODIFY COLUMN escopos TEXT NULL COMMENT 'Permissões concedidas (ex.: offline_access read write)';

SELECT 'V021 ok' AS resultado;
