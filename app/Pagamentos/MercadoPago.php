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
    public static function configurado(): bool
    {
        return filled(config('pagamentos.mercadopago.client_id')) && filled(config('pagamentos.mercadopago.client_secret'));
    }

    public static function retornoUrl(): string
    {
        return url(config('pagamentos.mercadopago.retorno_oauth'));
    }

    /** Endereço da tela de autorização do Mercado Pago (com PKCE S256). */
    public static function urlAutorizacao(string $state, string $codeVerifier): string
    {
        $desafio = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        return config('pagamentos.mercadopago.autorizacao_url').'?'.http_build_query([
            'client_id' => config('pagamentos.mercadopago.client_id'),
            'response_type' => 'code',
            'platform_id' => 'mp',
            'state' => $state,
            'redirect_uri' => self::retornoUrl(),
            'code_challenge' => $desafio,
            'code_challenge_method' => 'S256',
        ]);
    }

    /** Troca o "code" do retorno pelos tokens do organizador. */
    public static function trocarCodigo(string $code, string $codeVerifier): array
    {
        return self::token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::retornoUrl(),
            'code_verifier' => $codeVerifier,
        ]);
    }

    public static function renovarToken(string $refreshToken): array
    {
        return self::token(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
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
            'client_id' => config('pagamentos.mercadopago.client_id'),
            'client_secret' => config('pagamentos.mercadopago.client_secret'),
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
        $segredo = config('pagamentos.mercadopago.webhook_secret');
        if (! $segredo || ! $assinatura) {
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
