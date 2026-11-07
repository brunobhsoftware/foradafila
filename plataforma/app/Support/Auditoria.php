<?php

namespace App\Support;

use App\Models\Auditoria as Registro;
use Illuminate\Database\Eloquent\Model;

/** Grava na tabela auditoria quem fez o quê (regra: dados financeiros e de permissão). */
class Auditoria
{
    public static function registrar(string $acao, Model $registro, ?array $antes = null, ?array $depois = null): void
    {
        $oculto = ['senha_hash', 'totp_segredo_cript', 'access_token_cript', 'refresh_token_cript', 'convite_token_hash', 'api_chave_hash'];
        $limpar = fn (?array $dados) => $dados === null ? null : array_diff_key($dados, array_flip($oculto));

        $impersonacao = Esquema::temV008() ? Impersonacao::marcarAcao() : [];
        Registro::create($impersonacao + [
            'organizador_id' => app(Tenant::class)->id() ?? ($registro->organizador_id ?? null),
            'usuario_id' => auth()->id(),
            'acao' => $acao,
            'tabela' => $registro->getTable(),
            'registro_id' => $registro->getKey(),
            'antes' => $limpar($antes),
            'depois' => $limpar($depois ?? $registro->getAttributes()),
            'ip' => request()?->ip(),
        ]);
    }
}
