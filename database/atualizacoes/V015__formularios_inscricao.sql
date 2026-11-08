-- =====================================================================
-- Fora da Fila — V015: formulários de inscrição
-- A organização cria vários formulários (modelos, formulario.evento_id NULL) com os campos pedidos na compra;
-- cada evento escolhe um (evento.formulario_id; NULL = o formulário padrão da organização). Idempotente.
-- =====================================================================
ALTER TABLE formulario
  ADD COLUMN IF NOT EXISTS padrao TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = usado nos eventos que não escolheram outro';

ALTER TABLE evento
  ADD COLUMN IF NOT EXISTS formulario_id BIGINT UNSIGNED NULL COMMENT 'Formulário pedido na compra; NULL = padrão da organização',
  ADD INDEX IF NOT EXISTS ix_evento_formulario (formulario_id);

SELECT 'V015 ok' AS resultado;
