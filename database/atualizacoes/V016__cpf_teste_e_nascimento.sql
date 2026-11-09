-- V016 — Fora da Fila (09/11/2026)
-- 1) Limpa o CPF de teste do Mercado Pago (123.456.789-09, titular "APRO" dos cartões de teste), gravado por engano
--    no cadastro de quem fez compra de teste com cartão. Daqui em diante o CPF do titular só é gravado em vendas de produção.
-- 2) Data de nascimento no cadastro único (pessoa), para saber a idade. Em comprador a coluna já existe.
-- Idempotente: pode rodar mais de uma vez.

UPDATE comprador SET cpf = NULL WHERE cpf = '12345678909';
DELETE FROM pessoa WHERE cpf = '12345678909';

ALTER TABLE pessoa ADD COLUMN IF NOT EXISTS data_nascimento DATE NULL AFTER telefone;
