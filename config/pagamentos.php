<?php

// Credenciais das APLICAÇÕES nas operadoras de pagamento. Ficam só no .env do servidor.
// Os tokens de cada organizador ficam criptografados na tabela conta_pagamento.
// Estudo e fluxos: docs/integracao-pagamentos.md (V006 no banco).
return [
    'mercadopago' => [
        'ambiente' => env('MP_AMBIENTE', 'sandbox'),
        'client_id' => env('MP_CLIENT_ID'),
        'client_secret' => env('MP_CLIENT_SECRET'),
        'webhook_secret' => env('MP_WEBHOOK_SECRET'),
        'autorizacao_url' => env('MP_AUTORIZACAO_URL', 'https://auth.mercadopago.com/authorization'),
        'api_url' => env('MP_API_URL', 'https://api.mercadopago.com'),
        'retorno_oauth' => '/painel/financeiro/mercadopago/retorno',
        'webhook' => '/webhooks/mercadopago',
    ],
];
