-- =====================================================================
-- Fora da Fila — V003: ajuste de texto do modelo padrão de confirmação
-- Banco : u839930205_foradafila
-- Por quê: o modelo padrão do V001 dizia "Seus ingressos estão em anexo",
--          mas o ingresso não vai anexado ao e-mail: fica em Meus ingressos.
-- Só dados (sem mudança de estrutura). Idempotente.
-- =====================================================================

SET NAMES utf8mb4;

UPDATE modelo_mensagem
   SET corpo_texto = REPLACE(corpo_texto, 'Seus ingressos estão em anexo e em ', 'Seus ingressos estão em ')
 WHERE organizador_id IS NULL AND evento_id IS NULL AND tipo = 'confirmacao_compra' AND canal = 'email';

-- Conferência (esperado: 1 linha, sem a palavra "anexo")
SELECT id, tipo, corpo_texto LIKE '%anexo%' AS ainda_cita_anexo FROM modelo_mensagem
 WHERE organizador_id IS NULL AND tipo = 'confirmacao_compra';
