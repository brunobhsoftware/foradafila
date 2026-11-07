# Integração com operadoras de pagamento — estudo (1ª: Mercado Pago)

Revisão 1 — 07/11/2026. Base: documentação pública do Mercado Pago para desenvolvedores.
Pontos marcados **[confirmar]** precisam ser validados na conta de desenvolvedor e na homologação antes de
entrar em produção.

## 1. Modelo do Fora da Fila

- O dinheiro do ingresso cai **direto na conta do organizador** na operadora. O Fora da Fila é só a tecnologia.
- Cada organizador **conecta a própria conta** por autorização (OAuth): ele entra no Mercado Pago, autoriza o
  Fora da Fila, e a plataforma recebe um token para cobrar em nome dele. Ninguém digita chaves no painel.
- A comissão da plataforma pode ser retida na própria venda (**split**, campo `marketplace_fee`/`application_fee`
  do Mercado Pago) ou cobrada em **fatura mensal**. A decisão está pendente; o banco suporta os dois
  (`pagamento.comissao_retida` e as tabelas `fatura`/`taxa_lancamento`).

## 2. O que precisa existir no Mercado Pago (feito uma vez, pela BH Software)

1. Conta Mercado Pago da BH Software → **Suas integrações** → criar a aplicação "Fora da Fila"
   (modelo de integração: marketplace / pagamentos on-line). **[confirmar nome exato do modelo na tela]**
2. Na aplicação:
   - **Redirect URL** do OAuth: `https://foradafila.com.br/painel/financeiro/mercadopago/retorno`
   - **Webhooks**: URL `https://foradafila.com.br/webhooks/mercadopago`, eventos *Pagamentos* (e *Reembolsos /
     chargebacks*, se disponíveis), e gerar a **assinatura secreta**.
3. Guardar no `.env.foradafila` do servidor (nunca no Git):

   | Variável | O que é |
   | --- | --- |
   | `MP_CLIENT_ID` | Client ID da aplicação |
   | `MP_CLIENT_SECRET` | Client Secret da aplicação |
   | `MP_WEBHOOK_SECRET` | Assinatura secreta dos webhooks |
   | `MP_AMBIENTE` | `sandbox` (testes) ou `producao` |

   Os tokens de cada organizador ficam no banco **criptografados** com a `APP_KEY` do Laravel
   (`conta_pagamento.access_token_cript` / `refresh_token_cript`).
4. Contas de teste (comprador e vendedor) do painel do Mercado Pago, para a homologação.

## 3. Fluxos e campos

### 3.1 Conectar a conta do organizador (OAuth)

1. Botão "Conectar Mercado Pago" em Financeiro → redireciona para
   `https://auth.mercadopago.com/authorization?client_id=…&response_type=code&platform_id=mp&state=…&redirect_uri=…`
   (`state` aleatório guardado na sessão; PKCE `code_challenge` se a aplicação exigir). **[confirmar PKCE]**
2. O Mercado Pago volta com `code` → `POST https://api.mercadopago.com/oauth/token`
   (`grant_type=authorization_code`, `client_id`, `client_secret`, `code`, `redirect_uri`).
3. A resposta traz e o banco guarda:

   | Resposta | Coluna |
   | --- | --- |
   | `access_token` | `conta_pagamento.access_token_cript` (criptografado) |
   | `refresh_token` | `refresh_token_cript` (criptografado) |
   | `expires_in` (≈ 180 dias) | `token_expira_em` |
   | `user_id` | `conta_externa_id` |
   | `public_key` | `public_key` (para o formulário de cartão no navegador) |
   | `scope` | `escopos` (V006) |
   | `live_mode` | `ambiente` (`producao`/`sandbox`) |

   Dados da conta (`GET /users/me`): e-mail → `email_conta`, nickname → `apelido_externo`, site → `pais_conta` (V006).
4. Renovação: rotina do agendador renova o token antes de vencer (`grant_type=refresh_token`) e grava
   `token_renovado_em`; falha → `ultimo_erro`/`ultimo_erro_em` e aviso ao organizador.
5. Status da conta: `pendente → conectada → em_homologacao → homologada → ativa` (o trigger `trg_conta_bu`
   só deixa virar `homologada` com os 5 testes obrigatórios ok em `homologacao_teste`).
   Publicar evento com ingresso pago exige conta **ativa** em **produção** (já conferido na lista "Para publicar").

### 3.2 Venda (checkout)

Duas opções do Mercado Pago:

| | Checkout Pro (redireciona) | Checkout Transparente / Payments API |
| --- | --- | --- |
| Onde paga | Página do Mercado Pago | Dentro do foradafila.com.br |
| Pix / cartão / boleto | Sim | Sim (cartão via formulário seguro `Card Payment Brick`) |
| Esforço | Menor | Maior, mas experiência melhor |
| Campo | `pagamento.checkout_id` = `preference_id`, `link_pagamento` = `init_point` | `gateway_pagamento_id` = `id` do pagamento |

**Recomendação:** começar com **Checkout Pro** (mais rápido de homologar, cartão fica 100% com o Mercado Pago) e
depois oferecer Pix transparente (QR na própria página).

Para cada tentativa grava-se uma linha em `pagamento`:

| Mercado Pago | Coluna |
| --- | --- |
| `external_reference` (= `pedido.uuid`) | `referencia_externa` (V006) |
| `preference_id` | `checkout_id` (V006) |
| `id` do pagamento | `gateway_pagamento_id` |
| `order.id` / `merchant_order_id` | `gateway_pedido_id` (V006) |
| cabeçalho `X-Idempotency-Key` | `chave_idempotencia` (V006, único) |
| `payment_type_id` (`credit_card`, `debit_card`, `bank_transfer`=Pix, `ticket`=boleto) | `metodo` |
| `payment_method_id` (`pix`, `visa`, `master`…) | `meio_detalhe` (V006) |
| `installments` | `parcelas` |
| `status` | `status_gateway` (bruto) e `status` (nosso) |
| `status_detail` | `status_detalhe` (V006) |
| `transaction_amount` | `valor` |
| `fee_details` (tarifa do MP) | `tarifa_gateway` |
| `marketplace_fee` / `application_fee` | `comissao_retida` |
| `transaction_details.net_received_amount` | `valor_liquido_org` |
| `point_of_interaction.transaction_data.qr_code` | `pix_qr_code` (copia e cola; a imagem é gerada na tela) |
| `ticket_url` | `link_pagamento` (V006) |
| `date_of_expiration` | `expira_em` / `pix_expira_em` |
| `card.last_four_digits`, bandeira | `cartao_final`, `cartao_bandeira` (nunca o número) |
| `date_approved` | `aprovado_em` |
| `money_release_date` | `dinheiro_liberado_em` (V006) |
| resposta completa (sem dados de cartão) | `resposta_gateway` (V006) |

Tradução de status (Mercado Pago → `pagamento.status`):
`pending`→`pendente`, `in_process`/`authorized`→`em_analise`, `approved`→`aprovado`, `rejected`→`recusado`,
`cancelled`→`cancelado` (ou `expirado` se venceu), `refunded`→`estornado`, `charged_back`/`in_mediation`→`contestado`.

Pedido: `reservado` (estoque segurado, `expira_em`) → `aguardando_pagamento` → `pago` (emite ingressos e manda
e-mail) ou `expirado`/`recusado` (devolve o estoque). A rotina que expira reservas depende do cron.

### 3.3 Avisos da operadora (webhooks)

- `POST /webhooks/mercadopago` (sem login, sem CSRF), responde 200 rápido e processa depois.
- Conferência da assinatura: cabeçalho `x-signature` (`ts=…,v1=…`) = HMAC-SHA256, com `MP_WEBHOOK_SECRET`, de
  `id:{data.id};request-id:{x-request-id};ts:{ts};` → `webhook_evento.assinatura_valida`.
- Grava tudo em `webhook_evento` (`id_evento_externo` único evita processar duas vezes; `acao`, `recurso_id`,
  `request_id` do V006). Depois consulta `GET /v1/payments/{id}` com o token do organizador — **nunca** confia só
  no corpo do aviso.
- Conciliação diária (cron): busca pagamentos do dia por `external_reference` para pegar avisos perdidos.

### 3.4 Reembolso

- `POST /v1/payments/{id}/refunds` (total, ou parcial com `amount`) com `X-Idempotency-Key` →
  `reembolso.gateway_reembolso_id`, `chave_idempotencia`, `status_gateway`, `erro` (V006).
- Motivos já previstos: arrependimento (7 dias, CDC art. 49), evento cancelado/alterado, decisão do organizador.
- Prazo do Mercado Pago para reembolsar: até 180 dias após a compra. **[confirmar]**

## 4. Homologação (por conta de organizador)

Testes obrigatórios em `homologacao_teste` (o trigger exige os 5): `conexao_oauth`, `pix_aprovado`,
`cartao_aprovado`, `webhook_recebido`, `reembolso`. Opcionais: `cartao_recusado`, `split_comissao`.
Feitos com as contas de teste do Mercado Pago em `sandbox`.

## 5. Outras operadoras (mesma estrutura)

| | Mercado Pago | PagBank | PayPal |
| --- | --- | --- | --- |
| Conexão do vendedor | OAuth | PagBank Connect (OAuth) **[confirmar]** | Partner Referrals **[confirmar]** |
| Pix | Sim | Sim | Não no Brasil **[confirmar]** |
| Split | `marketplace_fee` | Divisão de pagamento **[confirmar]** | `platform_fees` **[confirmar]** |
| Webhook | `x-signature` HMAC | Notificação + consulta | Webhook com verificação |

O código deve ter uma interface única (`App\Pagamentos\Operadora`: conectar, renovar token, criar cobrança,
consultar, reembolsar, validar aviso) e uma implementação por operadora (`MercadoPago`, depois `PagBank`…).
`gateway.codigo` escolhe a implementação.

## 6. Segurança e LGPD

- Número de cartão **nunca** passa pelo servidor do Fora da Fila (formulário do Mercado Pago/Checkout Pro) →
  fora do escopo pesado do PCI.
- Tokens criptografados; logs sem token, CPF completo ou dados de cartão.
- `resposta_gateway` guarda a resposta já sem os dados do pagador além do necessário.

## 7. Ordem de implementação sugerida

1. Importar o **V006** no phpMyAdmin (só cria colunas; o site atual não muda).
2. Criar a aplicação no Mercado Pago e pôr `MP_*` no `.env.foradafila`.
3. Tela Financeiro → "Conectar Mercado Pago" (OAuth) e desconectar.
4. Checkout Pro + página de retorno + webhook + e-mail de ingressos pagos.
5. Homologação guiada na tela (os 5 testes) e ativação da conta.
6. Reembolso pelo painel. 7. Cron: expirar reservas, renovar tokens, conciliar.
