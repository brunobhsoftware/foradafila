-- =====================================================================
-- Fora da Fila — V005: capacidade máxima do evento passa a ser opcional
-- Banco : u839930205_foradafila
-- Decisão do Bruno (07/11/2026): sem capacidade informada, o produtor cadastra
-- quantos ingressos quiser, sob responsabilidade da empresa organizadora.
-- NULL = sem limite definido. O CHECK (capacidade > 0) continua valendo quando
-- houver valor, e o trigger trg_evento_bu só compara a soma dos lotes com a
-- capacidade quando ela existe (comparação com NULL não bloqueia).
-- Idempotente. No phpMyAdmin, rode com o banco selecionado (ou com USE antes).
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE evento
  MODIFY COLUMN capacidade INT UNSIGNED NULL
  COMMENT 'Lotação máxima; NULL = sem limite definido (responsabilidade do organizador)';

-- Conferência (esperado: "V005 ok")
SELECT 'V005 ok' AS resultado, COUNT(*) AS eventos, COUNT(capacidade) AS com_capacidade FROM evento;
