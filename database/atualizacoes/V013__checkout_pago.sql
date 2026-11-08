-- =====================================================================
-- Fora da Fila — V013: checkout pago
-- Guarda os participantes informados na compra enquanto o pagamento não é aprovado (os ingressos só são emitidos
-- quando o Mercado Pago confirma). Idempotente. Não consulta information_schema.
-- =====================================================================

ALTER TABLE pedido
  ADD COLUMN IF NOT EXISTS dados_participantes TEXT NULL
      COMMENT 'JSON com nome/CPF de cada ingresso até o pagamento ser aprovado';

-- O total do pedido inclui a taxa da plataforma quando ela é repassada ao comprador (V010).
ALTER TABLE pedido DROP CONSTRAINT IF EXISTS ck_pedido_valores;
ALTER TABLE pedido ADD CONSTRAINT ck_pedido_valores
  CHECK (valor_bruto >= 0 AND valor_desconto >= 0 AND valor_taxa_servico >= 0
         AND valor_total = valor_bruto - valor_desconto + valor_taxa_servico);

SELECT 'V013 ok' AS resultado;
