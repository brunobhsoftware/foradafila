-- =====================================================================
-- Fora da Fila — V010: quem paga a taxa da plataforma
-- O produtor escolhe se a taxa fica EMBUTIDA (sai do valor que ele recebe) ou é REPASSADA ao comprador
-- (somada ao preço; o produtor recebe o valor cheio). Padrão na organização; cada evento pode mudar.
-- O valor da taxa vem do motor de taxas da Administração (taxa_regra / taxa_organizador, V008).
-- Idempotente. Não consulta information_schema.
-- Importar no phpMyAdmin com o banco u839930205_foradafila selecionado.
-- =====================================================================

ALTER TABLE organizador
  ADD COLUMN IF NOT EXISTS taxa_comprador TINYINT(1) NOT NULL DEFAULT 0
      COMMENT 'Padrão da organização: 1 = repassa a taxa ao comprador; 0 = taxa embutida (produtor absorve)';

ALTER TABLE evento
  ADD COLUMN IF NOT EXISTS taxa_comprador TINYINT(1) NULL
      COMMENT 'NULL = usa o padrão da organização; 1 = repassa ao comprador; 0 = embutida';

ALTER TABLE pedido
  ADD COLUMN IF NOT EXISTS valor_taxa_servico DECIMAL(12,2) NOT NULL DEFAULT 0.00
      COMMENT 'Taxa paga pelo comprador (quando repassada); já incluída em valor_total' AFTER valor_desconto;

ALTER TABLE pedido_item
  ADD COLUMN IF NOT EXISTS taxa_unitaria DECIMAL(12,2) NOT NULL DEFAULT 0.00
      COMMENT 'Taxa por ingresso paga pelo comprador (quando repassada)' AFTER desconto_unitario;

-- Conferência (deve mostrar "V010 ok")
SELECT 'V010 ok' AS resultado, taxa_comprador FROM organizador LIMIT 1;
