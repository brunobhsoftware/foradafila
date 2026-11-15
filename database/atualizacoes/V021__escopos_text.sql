-- Fora da Fila — V021: escopos de VARCHAR(255) para TEXT.
-- O Mercado Pago retorna muitos escopos na conexão OAuth e o campo não cabia.
-- Idempotente.

ALTER TABLE conta_pagamento
  MODIFY COLUMN escopos TEXT NULL COMMENT 'Permissões concedidas (ex.: offline_access read write)';

SELECT 'V021 ok' AS resultado;
