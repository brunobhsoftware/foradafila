<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Código de 6 dígitos enviado por e-mail para confirmar ações sensíveis da conta
 * (trocar senha, trocar e-mail). Fica só o hash no cache, vale 15 minutos,
 * aceita no máximo 5 tentativas e é descartado depois de usado.
 */
class CodigoVerificacao
{
    public const VALIDADE_MIN = 15;

    private const MAX_TENTATIVAS = 5;

    private const INTERVALO_REENVIO_S = 60;

    private static function chave(int $usuarioId, string $finalidade): string
    {
        return "codigo-verificacao:{$finalidade}:{$usuarioId}";
    }

    /** Gera um código novo (o anterior deixa de valer). Retorna null se o último foi enviado há menos de 60 s. */
    public static function gerar(int $usuarioId, string $finalidade): ?string
    {
        $atual = Cache::get(self::chave($usuarioId, $finalidade));
        if ($atual && now()->timestamp - $atual['gerado_em'] < self::INTERVALO_REENVIO_S) {
            return null;
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put(self::chave($usuarioId, $finalidade), [
            'hash' => hash_hmac('sha256', $codigo, (string) config('app.key')),
            'tentativas' => 0,
            'gerado_em' => now()->timestamp,
        ], now()->addMinutes(self::VALIDADE_MIN));

        return $codigo;
    }

    public static function pendente(int $usuarioId, string $finalidade): bool
    {
        return Cache::has(self::chave($usuarioId, $finalidade));
    }

    /**
     * Confere o código. Em caso de acerto, descarta o código (uso único).
     *
     * @return string|null mensagem de erro, ou null se o código confere
     */
    public static function conferir(int $usuarioId, string $finalidade, string $codigo): ?string
    {
        $chave = self::chave($usuarioId, $finalidade);
        $dados = Cache::get($chave);
        if (! $dados) {
            return 'O código expirou ou não foi pedido. Envie um novo código.';
        }
        if ($dados['tentativas'] >= self::MAX_TENTATIVAS) {
            Cache::forget($chave);

            return 'Muitas tentativas com código errado. Envie um novo código.';
        }

        $codigo = preg_replace('/\D/', '', $codigo);
        if (! hash_equals($dados['hash'], hash_hmac('sha256', $codigo, (string) config('app.key')))) {
            $dados['tentativas']++;
            Cache::put($chave, $dados, now()->addMinutes(self::VALIDADE_MIN));
            $restam = self::MAX_TENTATIVAS - $dados['tentativas'];

            return $restam > 0 ? "Código incorreto. Restam {$restam} tentativa(s)." : 'Muitas tentativas com código errado. Envie um novo código.';
        }

        Cache::forget($chave);

        return null;
    }
}
