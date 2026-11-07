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
            self::$cache[$nome] = $valor ?: config('pagamentos.mercadopago.'.$nome);
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
        $segredo = self::credencial('webhook_secret');
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
