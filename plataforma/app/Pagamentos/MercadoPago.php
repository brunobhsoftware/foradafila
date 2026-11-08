<?php

namespace App\Pagamentos;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente do Mercado Pago: OAuth do vendedor (organizador) e conferência dos avisos (webhooks).
 * Credenciais da aplicação vêm do .env (MP_CLIENT_ID, MP_CLIENT_SECRET, MP_WEBHOOK_SECRET).
 * Fluxos e campos: docs/integracao-pagamentos.md.
 */
class MercadoPago
{
    /** Chaves da aplicação: cadastradas pelo administrador da plataforma (parametro_sistema, segredos criptografados) ou no .env. */
    public const CHAVES = [
        'client_id' => ['parametro' => 'mp_client_id', 'secreto' => false, 'env' => 'MP_CLIENT_ID'],
        'client_secret' => ['parametro' => 'mp_client_secret', 'secreto' => true, 'env' => 'MP_CLIENT_SECRET'],
        'webhook_secret' => ['parametro' => 'mp_webhook_secret', 'secreto' => true, 'env' => 'MP_WEBHOOK_SECRET'],
        'access_token' => ['parametro' => 'mp_access_token', 'secreto' => true, 'env' => 'MP_ACCESS_TOKEN'],
        // Credencial de TESTE da aplicação (Credenciais > Teste > Access Token): usada só no pagamento de teste.
        'teste_access_token' => ['parametro' => 'mp_teste_access_token', 'secreto' => true, 'env' => 'MP_TESTE_ACCESS_TOKEN'],
        'teste_webhook_secret' => ['parametro' => 'mp_teste_webhook_secret', 'secreto' => true, 'env' => 'MP_TESTE_WEBHOOK_SECRET'],
        'teste_client_id' => ['parametro' => 'mp_teste_client_id', 'secreto' => false, 'env' => 'MP_TESTE_CLIENT_ID'],
        'teste_client_secret' => ['parametro' => 'mp_teste_client_secret', 'secreto' => true, 'env' => 'MP_TESTE_CLIENT_SECRET'],
    ];

    private static array $cache = [];

    public static function credencial(string $nome): ?string
    {
        if (! array_key_exists($nome, self::$cache)) {
            $def = self::CHAVES[$nome];
            $valor = null;
            try {
                $bruto = \Illuminate\Support\Facades\DB::table('parametro_sistema')->where('chave', $def['parametro'])->value('valor');
                if (filled($bruto)) {
                    $valor = $def['secreto'] ? decrypt($bruto, false) : $bruto;
                }
            } catch (\Throwable $e) {
                $valor = null; // tabela indisponível ou APP_KEY trocada: cai no .env
            }
            self::$cache[$nome] = $valor ?: (config('pagamentos.mercadopago.'.$nome) ?: env($def['env']));
        }

        return self::$cache[$nome];
    }

    /** Grava as chaves (só administrador da plataforma). Valor vazio mantém o atual. */
    public static function salvarCredenciais(array $valores): void
    {
        foreach (self::CHAVES as $nome => $def) {
            $v = trim((string) ($valores[$nome] ?? ''));
            if ($v === '') {
                continue;
            }
            \Illuminate\Support\Facades\DB::table('parametro_sistema')->updateOrInsert(['chave' => $def['parametro']], [
                'valor' => $def['secreto'] ? encrypt($v, false) : $v,
                'descricao' => 'Mercado Pago: '.$def['env'].($def['secreto'] ? ' (criptografado)' : ''),
            ]);
        }
        self::$cache = [];
    }

    /** Situação de cada chave, sem revelar os segredos. */
    public static function situacaoCredenciais(): array
    {
        $r = [];
        foreach (self::CHAVES as $nome => $def) {
            $v = self::credencial($nome);
            $r[$nome] = ['definida' => filled($v), 'dica' => filled($v) ? ($def['secreto'] ? '••••'.substr($v, -4) : $v) : null];
        }

        return $r;
    }

    /** PKCE é opcional no Mercado Pago e precisa estar ativado também na aplicação ("Detalhes da aplicação" > Editar). */
    public static function usaPkce(): bool
    {
        try {
            return \Illuminate\Support\Facades\DB::table('parametro_sistema')->where('chave', 'mp_pkce')->value('valor') === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function definirPkce(bool $ligado): void
    {
        \Illuminate\Support\Facades\DB::table('parametro_sistema')->updateOrInsert(['chave' => 'mp_pkce'],
            ['valor' => $ligado ? '1' : '0', 'descricao' => 'Mercado Pago: usar PKCE no OAuth (precisa estar ativado também na aplicação)']);
    }

    public static function configurado(): bool
    {
        return filled(self::credencial('client_id')) && filled(self::credencial('client_secret'));
    }

    public static function retornoUrl(): string
    {
        return url(config('pagamentos.mercadopago.retorno_oauth'));
    }

    /** Endereço da tela de autorização do Mercado Pago (PKCE S256 só se ativado na Administração). */
    public static function urlAutorizacao(string $state, string $codeVerifier): string
    {
        $params = [
            'client_id' => self::credencial('client_id'),
            'response_type' => 'code',
            'platform_id' => 'mp',
            'state' => $state,
            'redirect_uri' => self::retornoUrl(),
        ];
        if (self::usaPkce()) {
            $params['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
            $params['code_challenge_method'] = 'S256';
        }

        return config('pagamentos.mercadopago.autorizacao_url').'?'.http_build_query($params);
    }

    /** Troca o "code" do retorno pelos tokens do organizador. */
    public static function trocarCodigo(string $code, string $codeVerifier): array
    {
        return self::token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::retornoUrl(),
        ] + (self::usaPkce() ? ['code_verifier' => $codeVerifier] : []));
    }

    public static function renovarToken(string $refreshToken): array
    {
        return self::token(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    /** Token da própria aplicação (fluxo client_credentials), para operar na conta da BH Software. Guardado 5 h em cache. */
    public static function tokenAplicacao(): string
    {
        if ($t = self::credencial('access_token')) {
            return $t; // Access Token de produção cadastrado
        }

        return \Illuminate\Support\Facades\Cache::remember('mp_token_aplicacao_'.md5((string) self::credencial('client_id')), now()->addHours(5),
            fn () => self::token(['grant_type' => 'client_credentials'])['access_token']);
    }

    /** Token do ambiente de TESTE: o Access Token de teste cadastrado ou, sem ele, client_credentials com o Client ID/Secret de teste. */
    public static function tokenTeste(): ?string
    {
        if ($t = self::credencial('teste_access_token')) {
            return $t;
        }
        $id = self::credencial('teste_client_id');
        $segredo = self::credencial('teste_client_secret');
        if (! $id || ! $segredo) {
            return null;
        }

        return \Illuminate\Support\Facades\Cache::remember('mp_token_teste_'.md5($id), now()->addHours(5), function () use ($id, $segredo) {
            $r = Http::asForm()->acceptJson()->timeout(20)->post(config('pagamentos.mercadopago.api_url').'/oauth/token',
                ['grant_type' => 'client_credentials', 'client_id' => $id, 'client_secret' => $segredo]);
            if (! $r->successful() || ! $r->json('access_token')) {
                throw new RuntimeException('O Mercado Pago recusou o Client ID/Secret de teste (HTTP '.$r->status().').');
            }

            return $r->json('access_token');
        });
    }

    /** Checkout Pro: cria a preferência e devolve a resposta (init_point, id...). */
    public static function criarPreferencia(string $accessToken, array $dados): array
    {
        $r = Http::withToken($accessToken)->acceptJson()->asJson()->timeout(20)
            ->withHeaders(['X-Idempotency-Key' => (string) \Illuminate\Support\Str::uuid()])
            ->post(config('pagamentos.mercadopago.api_url').'/checkout/preferences', $dados);
        if (! $r->successful() || ! $r->json('id')) {
            throw new RuntimeException('O Mercado Pago recusou a criação do pagamento (HTTP '.$r->status().($r->json('message') ? ': '.mb_substr((string) $r->json('message'), 0, 200) : '').').');
        }

        return $r->json();
    }

    /** API de Orders (Checkout Pro com Orders): cria a order e devolve a resposta (id, checkout_url...). */
    /** Prepara o SDK oficial (mercadopago/dx-php) com o token e a URL da API (trocada só nos testes). */
    private static function sdk(string $accessToken): void
    {
        \MercadoPago\MercadoPagoConfig::setAccessToken($accessToken);
        \MercadoPago\MercadoPagoConfig::$BASE_URL = rtrim((string) config('pagamentos.mercadopago.api_url'), '/');
        \MercadoPago\MercadoPagoConfig::setRuntimeEnviroment(\MercadoPago\MercadoPagoConfig::SERVER);
    }

    /** Cria a order pelo SDK oficial do Mercado Pago (recomendação da Qualidade da integração). */
    public static function criarOrder(string $accessToken, array $dados): array
    {
        self::sdk($accessToken);
        $opcoes = new \MercadoPago\Client\Common\RequestOptions();
        $opcoes->setCustomHeaders(['X-Idempotency-Key: '.\Illuminate\Support\Str::uuid()]);
        try {
            $order = (new \MercadoPago\Client\Order\OrderClient())->create($dados, $opcoes);
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            $r = $e->getApiResponse();
            $j = $r ? $r->getContent() : [];
            // Mensagem + detalhes de cada erro (ex.: quais propriedades faltam), para saber o que corrigir.
            $partes = collect((array) ($j['errors'] ?? []))->map(fn ($er) => trim(($er['message'] ?? '').' '.implode(', ', array_map(
                fn ($d) => is_scalar($d) ? (string) $d : json_encode($d, JSON_UNESCAPED_UNICODE), (array) ($er['details'] ?? [])))))->filter();
            $msg = trim(($j['message'] ?? '').($partes->isNotEmpty() ? ' — '.$partes->implode('; ') : ''));
            \Illuminate\Support\Facades\Log::warning('Mercado Pago recusou order', ['status' => $e->getStatusCode(), 'resposta' => $j]);
            throw new RuntimeException('O Mercado Pago recusou a criação da order (HTTP '.$e->getStatusCode().($msg ? ': '.mb_substr($msg, 0, 500) : '').').');
        } catch (\Throwable $e) {
            throw new RuntimeException('Não foi possível falar com o Mercado Pago: '.mb_substr($e->getMessage(), 0, 200));
        }
        $conteudo = $order->getResponse()->getContent();
        if (empty($conteudo['id'])) {
            throw new RuntimeException('O Mercado Pago não devolveu o número da order.');
        }

        return $conteudo;
    }

    /** Devolução da order (total sem $pedido; parcial com transactions.payments[{id, amount}]). */
    public static function reembolsarOrder(string $accessToken, string $id, ?array $pedido = null): array
    {
        self::sdk($accessToken);
        $opcoes = new \MercadoPago\Client\Common\RequestOptions();
        $opcoes->setCustomHeaders(['X-Idempotency-Key: '.\Illuminate\Support\Str::uuid()]);
        try {
            return (new \MercadoPago\Client\Order\OrderClient())->refund($id, $pedido, $opcoes)->getResponse()->getContent();
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            $j = $e->getApiResponse()?->getContent() ?? [];
            $msg = $j['message'] ?? collect((array) ($j['errors'] ?? []))->pluck('message')->filter()->implode('; ');
            \Illuminate\Support\Facades\Log::warning('Mercado Pago recusou devolução', ['order' => $id, 'resposta' => $j]);
            throw new RuntimeException('O Mercado Pago recusou a devolução (HTTP '.$e->getStatusCode().($msg ? ': '.mb_substr((string) $msg, 0, 300) : '').').');
        }
    }

    public static function order(string $accessToken, string $id): array
    {
        self::sdk($accessToken);
        try {
            return (new \MercadoPago\Client\Order\OrderClient())->get($id)->getResponse()->getContent();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function pagamento(string $accessToken, string $id): array
    {
        $r = Http::withToken($accessToken)->acceptJson()->timeout(15)->get(config('pagamentos.mercadopago.api_url').'/v1/payments/'.rawurlencode($id));

        return $r->successful() ? $r->json() : [];
    }

    /** Dados da conta conectada (e-mail, apelido, país). Falha aqui não impede a conexão. */
    public static function usuario(string $accessToken): array
    {
        $r = Http::withToken($accessToken)->acceptJson()->timeout(15)->get(config('pagamentos.mercadopago.api_url').'/users/me');

        return $r->successful() ? $r->json() : [];
    }

    private static function token(array $dados): array
    {
        $r = Http::asForm()->acceptJson()->timeout(20)->post(config('pagamentos.mercadopago.api_url').'/oauth/token', $dados + [
            'client_id' => self::credencial('client_id'),
            'client_secret' => self::credencial('client_secret'),
        ]);
        if (! $r->successful() || ! $r->json('access_token')) {
            // Sem tokens nem segredos na mensagem.
            throw new RuntimeException('O Mercado Pago recusou a autorização (HTTP '.$r->status().($r->json('message') ? ': '.mb_substr((string) $r->json('message'), 0, 200) : '').').');
        }

        return $r->json();
    }

    /**
     * Confere o cabeçalho x-signature ("ts=...,v1=...") do aviso:
     * HMAC-SHA256 de "id:{data.id};request-id:{x-request-id};ts:{ts};" com MP_WEBHOOK_SECRET.
     */
    public static function assinaturaValida(?string $assinatura, ?string $requestId, ?string $dataId): bool
    {
        return self::origemAssinatura($assinatura, $requestId, $dataId) !== null;
    }

    /** "producao" ou "teste" conforme a assinatura secreta que confere; null se nenhuma confere. */
    public static function origemAssinatura(?string $assinatura, ?string $requestId, ?string $dataId): ?string
    {
        foreach (['producao' => 'webhook_secret', 'teste' => 'teste_webhook_secret'] as $origem => $chave) {
            $segredo = self::credencial($chave);
            if ($segredo && self::confereAssinatura($segredo, $assinatura, $requestId, $dataId)) {
                return $origem;
            }
        }

        return null;
    }

    public static function confereAssinatura(string $segredo, ?string $assinatura, ?string $requestId, ?string $dataId): bool
    {
        if (! $assinatura) {
            return false;
        }
        $partes = [];
        foreach (explode(',', $assinatura) as $par) {
            [$k, $v] = array_pad(explode('=', trim($par), 2), 2, null);
            $partes[$k] = $v;
        }
        if (empty($partes['ts']) || empty($partes['v1'])) {
            return false;
        }
        $manifesto = '';
        if ($dataId !== null && $dataId !== '') {
            $manifesto .= 'id:'.(ctype_alnum($dataId) ? strtolower($dataId) : $dataId).';';
        }
        if ($requestId) {
            $manifesto .= 'request-id:'.$requestId.';';
        }
        $manifesto .= 'ts:'.$partes['ts'].';';

        return hash_equals(hash_hmac('sha256', $manifesto, $segredo), $partes['v1']);
    }
}
