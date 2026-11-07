-- =====================================================================
-- Fora da Fila — V004: dados do organizador e preferências de notificação
-- Banco : u839930205_foradafila
-- Acrescenta (sem apagar nada):
--   organizador: representante legal e CPF, dados bancários (banco, agência,
--                conta, favorecido), chave de acesso da API (só o hash; campo
--                bloqueado até existir o token de integração) e a opção
--                "Mostrar eventos passados" na página pública.
--   organizador_usuario: preferências de notificação de cada pessoa da equipe.
-- A logomarca usa a coluna organizador.logo_url, que já existe.
-- As colunas novas aceitam NULL no banco para não quebrar cadastros existentes;
-- a obrigatoriedade é conferida pela aplicação ao salvar a organização.
-- Idempotente (ADD COLUMN IF NOT EXISTS): pode ser executado mais de uma vez.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE organizador
  ADD COLUMN IF NOT EXISTS representante_nome   VARCHAR(150) NULL COMMENT 'Representante legal' AFTER nome_fantasia,
  ADD COLUMN IF NOT EXISTS representante_cpf    CHAR(11)     NULL COMMENT 'CPF do representante legal, só dígitos' AFTER representante_nome,
  ADD COLUMN IF NOT EXISTS banco                VARCHAR(80)  NULL COMMENT 'Ex.: 341 - Itaú Unibanco' AFTER email_financeiro,
  ADD COLUMN IF NOT EXISTS agencia              VARCHAR(10)  NULL AFTER banco,
  ADD COLUMN IF NOT EXISTS conta                VARCHAR(20)  NULL COMMENT 'Número da conta com dígito' AFTER agencia,
  ADD COLUMN IF NOT EXISTS favorecido           VARCHAR(150) NULL COMMENT 'Titular da conta' AFTER conta,
  ADD COLUMN IF NOT EXISTS api_chave_hash       CHAR(64)     NULL COMMENT 'SHA-256 da chave de acesso da API; a chave nunca é guardada' AFTER favorecido,
  ADD COLUMN IF NOT EXISTS api_chave_prefixo    VARCHAR(12)  NULL COMMENT 'Início da chave, para identificação na tela' AFTER api_chave_hash,
  ADD COLUMN IF NOT EXISTS api_chave_gerada_em  DATETIME     NULL AFTER api_chave_prefixo,
  ADD COLUMN IF NOT EXISTS mostrar_eventos_passados TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Página pública lista também os eventos já realizados' AFTER api_chave_gerada_em;

ALTER TABLE organizador
  ADD UNIQUE INDEX IF NOT EXISTS uq_organizador_api_chave (api_chave_hash);

ALTER TABLE organizador_usuario
  ADD COLUMN IF NOT EXISTS notificar_compra      TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'E-mail a cada compra/inscrição' AFTER status,
  ADD COLUMN IF NOT EXISTS receber_novidades_em  DATETIME   NULL COMMENT 'Consentimento para novidades do Fora da Fila (LGPD); NULL = não' AFTER notificar_compra;

-- Conferência (esperado: 12 colunas)
SELECT COUNT(*) AS colunas_novas FROM information_schema.columns
 WHERE table_schema = DATABASE()
   AND ((table_name = 'organizador' AND column_name IN ('representante_nome','representante_cpf','banco','agencia','conta',
          'favorecido','api_chave_hash','api_chave_prefixo','api_chave_gerada_em','mostrar_eventos_passados'))
     OR (table_name = 'organizador_usuario' AND column_name IN ('notificar_compra','receber_novidades_em')));
