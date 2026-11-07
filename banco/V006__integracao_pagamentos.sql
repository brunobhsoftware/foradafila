-- =====================================================================
-- Fora da Fila — V006: campos para a integração com operadoras de pagamento
-- 1ª integração: Mercado Pago (OAuth do vendedor + Checkout/Payments API + Webhooks).
-- Os nomes são genéricos para servir também a PagBank, PayPal e outras.
-- Estudo completo: docs/integracao-pagamentos.md
--
-- Idempotente (ADD COLUMN IF NOT EXISTS). Não consulta information_schema.
-- Importar no phpMyAdmin com o banco u839930205_foradafila selecionado.
-- Credenciais da APLICAÇÃO (client_id, client_secret, chave do webhook) NÃO ficam no
-- banco: ficam no .env do servidor. Tokens de cada organizador ficam criptografados.
-- =====================================================================

-- ---------- Operadoras: o que cada uma oferece ----------
ALTER TABLE gateway
  ADD COLUMN IF NOT EXISTS suporta_oauth      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Organizador conecta a conta por autorização (sem copiar chaves)' AFTER suporta_boleto,
  ADD COLUMN IF NOT EXISTS suporta_reembolso  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Reembolso total/parcial pela API' AFTER suporta_oauth,
  ADD COLUMN IF NOT EXISTS suporta_webhook    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Avisa mudanças de status por notificação' AFTER suporta_reembolso,
  ADD COLUMN IF NOT EXISTS parcelas_max       TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Máximo de parcelas no cartão' AFTER suporta_webhook,
  ADD COLUMN IF NOT EXISTS documentacao_url   VARCHAR(255) NULL AFTER parcelas_max;

UPDATE gateway SET suporta_oauth = 1, suporta_reembolso = 1, suporta_webhook = 1, parcelas_max = 12,
       documentacao_url = 'https://www.mercadopago.com.br/developers/pt/docs'
 WHERE codigo = 'mercadopago';
UPDATE gateway SET suporta_reembolso = 1, suporta_webhook = 1,
       documentacao_url = 'https://developer.pagbank.com.br/'
 WHERE codigo = 'pagseguro';
UPDATE gateway SET suporta_reembolso = 1, suporta_webhook = 1,
       documentacao_url = 'https://developer.paypal.com/'
 WHERE codigo = 'paypal';

-- ---------- Conta de recebimento do organizador (conexão OAuth) ----------
ALTER TABLE conta_pagamento
  ADD COLUMN IF NOT EXISTS apelido_externo     VARCHAR(100) NULL COMMENT 'Nome/nickname da conta na operadora' AFTER email_conta,
  ADD COLUMN IF NOT EXISTS pais_conta          VARCHAR(10)  NULL COMMENT 'Site/país da conta (Mercado Pago: MLB = Brasil)' AFTER apelido_externo,
  ADD COLUMN IF NOT EXISTS escopos             VARCHAR(255) NULL COMMENT 'Permissões concedidas (ex.: offline_access read write)' AFTER token_expira_em,
  ADD COLUMN IF NOT EXISTS token_renovado_em   DATETIME     NULL AFTER escopos,
  ADD COLUMN IF NOT EXISTS conectado_por       BIGINT UNSIGNED NULL COMMENT 'Usuário que autorizou a conexão' AFTER token_renovado_em,
  ADD COLUMN IF NOT EXISTS conectado_em        DATETIME     NULL AFTER conectado_por,
  ADD COLUMN IF NOT EXISTS desconectado_em     DATETIME     NULL AFTER conectado_em,
  ADD COLUMN IF NOT EXISTS ultimo_erro         VARCHAR(500) NULL COMMENT 'Último erro de token/conexão (sem dados sensíveis)' AFTER desconectado_em,
  ADD COLUMN IF NOT EXISTS ultimo_erro_em      DATETIME     NULL AFTER ultimo_erro;

-- ---------- Pagamento (uma tentativa de cobrança de um pedido) ----------
ALTER TABLE pagamento
  ADD COLUMN IF NOT EXISTS referencia_externa   VARCHAR(64)  NULL COMMENT 'Enviada à operadora (external_reference = uuid do pedido)' AFTER gateway_pagamento_id,
  ADD COLUMN IF NOT EXISTS checkout_id          VARCHAR(100) NULL COMMENT 'Preferência/sessão de checkout (Mercado Pago: preference_id)' AFTER referencia_externa,
  ADD COLUMN IF NOT EXISTS gateway_pedido_id    VARCHAR(100) NULL COMMENT 'Pedido na operadora (Mercado Pago: merchant_order_id)' AFTER checkout_id,
  ADD COLUMN IF NOT EXISTS chave_idempotencia   CHAR(36)     NULL COMMENT 'Enviada no cabeçalho X-Idempotency-Key' AFTER gateway_pedido_id,
  ADD COLUMN IF NOT EXISTS meio_detalhe         VARCHAR(40)  NULL COMMENT 'Ex.: pix, visa, master, bolbradesco (payment_method_id)' AFTER metodo,
  ADD COLUMN IF NOT EXISTS status_detalhe       VARCHAR(80)  NULL COMMENT 'Motivo detalhado (status_detail), ex.: cc_rejected_insufficient_amount' AFTER status_gateway,
  ADD COLUMN IF NOT EXISTS link_pagamento       VARCHAR(500) NULL COMMENT 'Página de pagamento/comprovante (init_point, ticket_url)' AFTER boleto_url,
  ADD COLUMN IF NOT EXISTS expira_em            DATETIME     NULL COMMENT 'Vencimento do Pix/boleto (date_of_expiration)' AFTER link_pagamento,
  ADD COLUMN IF NOT EXISTS dinheiro_liberado_em DATETIME     NULL COMMENT 'Quando o valor fica disponível ao organizador (money_release_date)' AFTER aprovado_em,
  ADD COLUMN IF NOT EXISTS resposta_gateway     JSON         NULL COMMENT 'Última resposta da operadora, sem dados de cartão' AFTER dinheiro_liberado_em;

ALTER TABLE pagamento
  ADD UNIQUE KEY IF NOT EXISTS uq_pagamento_idempotencia (chave_idempotencia),
  ADD KEY IF NOT EXISTS ix_pagamento_referencia (referencia_externa);

-- ---------- Reembolso ----------
ALTER TABLE reembolso
  ADD COLUMN IF NOT EXISTS chave_idempotencia CHAR(36)    NULL COMMENT 'Enviada no cabeçalho X-Idempotency-Key' AFTER gateway_reembolso_id,
  ADD COLUMN IF NOT EXISTS status_gateway     VARCHAR(60) NULL AFTER status,
  ADD COLUMN IF NOT EXISTS erro               VARCHAR(500) NULL AFTER status_gateway;

ALTER TABLE reembolso
  ADD UNIQUE KEY IF NOT EXISTS uq_reembolso_idempotencia (chave_idempotencia);

-- ---------- Avisos (webhooks) recebidos ----------
ALTER TABLE webhook_evento
  ADD COLUMN IF NOT EXISTS acao        VARCHAR(60)  NULL COMMENT 'Ex.: payment.created, payment.updated' AFTER tipo,
  ADD COLUMN IF NOT EXISTS recurso_id  VARCHAR(100) NULL COMMENT 'Id do recurso avisado (data.id)' AFTER acao,
  ADD COLUMN IF NOT EXISTS request_id  VARCHAR(100) NULL COMMENT 'Cabeçalho x-request-id (entra na conferência da assinatura)' AFTER recurso_id;

ALTER TABLE webhook_evento
  ADD KEY IF NOT EXISTS ix_webhook_recurso (gateway_id, recurso_id);

-- ---------- Conferência (deve mostrar "V006 ok") ----------
SELECT IF(COUNT(*) = 1, 'V006 ok', 'V006 FALHOU') AS resultado
  FROM gateway WHERE codigo = 'mercadopago' AND suporta_oauth = 1;
SELECT id, referencia_externa, checkout_id, meio_detalhe, status_detalhe, dinheiro_liberado_em FROM pagamento LIMIT 1;
SELECT id, apelido_externo, escopos, conectado_em FROM conta_pagamento LIMIT 1;
SELECT id, acao, recurso_id, request_id FROM webhook_evento LIMIT 1;
