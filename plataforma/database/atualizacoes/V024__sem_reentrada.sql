-- Fora da Fila — V024: Remove a reentrada (sair e voltar) de todos os eventos — a opção saiu do sistema (decisão do Bruno, 18/11/2026). Idempotente
UPDATE evento SET permite_reentrada = 0 WHERE permite_reentrada <> 0;
UPDATE ingresso SET dentro_do_evento = 0 WHERE dentro_do_evento <> 0;
SELECT 'V024 ok' AS resultado;
