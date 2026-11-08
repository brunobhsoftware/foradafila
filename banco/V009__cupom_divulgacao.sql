-- =====================================================================
-- Fora da Fila — V009: cupom do tipo "divulgação"
-- Código só para medir quantos ingressos cada pessoa divulgou: sem desconto, sem limites e sem validade.
-- Idempotente. Não consulta information_schema.
-- Importar no phpMyAdmin com o banco u839930205_foradafila selecionado.
-- =====================================================================

ALTER TABLE cupom
  MODIFY COLUMN tipo_desconto ENUM('percentual','valor_fixo','divulgacao') NOT NULL;

-- O cupom de divulgação tem valor 0: a regra antiga exigia valor > 0.
ALTER TABLE cupom DROP CONSTRAINT IF EXISTS ck_cupom_valor;
ALTER TABLE cupom ADD CONSTRAINT ck_cupom_valor CHECK (
  (tipo_desconto = 'divulgacao' AND valor = 0)
  OR (tipo_desconto <> 'divulgacao' AND valor > 0 AND (tipo_desconto <> 'percentual' OR valor <= 100)));

-- Conferência (deve mostrar "V009 ok")
SHOW COLUMNS FROM cupom LIKE 'tipo_desconto';
SELECT 'V009 ok' AS resultado;
