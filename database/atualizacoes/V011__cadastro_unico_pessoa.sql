-- =====================================================================
-- Fora da Fila — V011: cadastro único de pessoas por CPF
-- Toda vez que alguém informa CPF com nome/e-mail/telefone (participante, representante legal, pagamento),
-- os dados ficam aqui, com o CPF como chave. A equipe logada pode buscar pelo CPF e o formulário se preenche.
-- LGPD: dado pessoal; consulta só por usuário logado, registrada na auditoria (pessoa.consulta).
-- Idempotente: pode rodar mais de uma vez.
-- =====================================================================
CREATE TABLE IF NOT EXISTS pessoa (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  cpf CHAR(11) NOT NULL,
  nome VARCHAR(150) NOT NULL,
  email VARCHAR(150) NULL,
  telefone VARCHAR(20) NULL,
  origem VARCHAR(40) NOT NULL COMMENT 'onde foi informado pela primeira vez',
  ultima_origem VARCHAR(40) NULL,
  anonimizado_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pessoa_uuid (uuid),
  UNIQUE KEY uq_pessoa_cpf (cpf),
  KEY ix_pessoa_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'V011 ok' AS resultado;
