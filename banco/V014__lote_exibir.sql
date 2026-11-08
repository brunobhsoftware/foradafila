-- =====================================================================
-- Fora da Fila — V014: exibir ou ocultar o ingresso na página do evento
-- Independe de "Ingresso ativo" (vender ou não): um lote pode estar à venda só por link/cupom e oculto da página,
-- ou visível como "em breve"/"esgotado". Padrão: exibir. Idempotente.
-- =====================================================================
ALTER TABLE lote
  ADD COLUMN IF NOT EXISTS exibir TINYINT(1) NOT NULL DEFAULT 1
      COMMENT '1 = aparece na página do evento; 0 = oculto (não aparece nem é vendido pela página)';

SELECT 'V014 ok' AS resultado;
