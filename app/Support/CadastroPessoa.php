<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cadastro único de pessoas por CPF (V011). Grava/atualiza sempre que alguém informa CPF + dados,
 * sem apagar o que já existe com valor vazio. Sem o V011 não faz nada.
 */
class CadastroPessoa
{
    public static function registrar(?string $cpf, ?string $nome, ?string $email = null, ?string $telefone = null, string $origem = 'site'): void
    {
        $cpf = preg_replace('/\D/', '', (string) $cpf);
        $nome = trim((string) $nome);
        if (strlen($cpf) !== 11 || $nome === '' || ! Esquema::temV011()) {
            return;
        }
        $novos = array_filter([
            'nome' => mb_substr($nome, 0, 150),
            'email' => $email ? mb_strtolower(trim($email)) : null,
            'telefone' => preg_replace('/\D/', '', (string) $telefone) ?: null,
        ]);
        try {
            $existe = DB::table('pessoa')->where('cpf', $cpf)->exists();
            if ($existe) {
                DB::table('pessoa')->where('cpf', $cpf)->whereNull('anonimizado_em')->update($novos + ['ultima_origem' => $origem]);
            } else {
                DB::table('pessoa')->insert($novos + ['uuid' => (string) Str::uuid(), 'cpf' => $cpf, 'origem' => $origem]);
            }
        } catch (\Throwable $e) {
            report($e); // o cadastro único nunca impede a operação principal
        }
    }

    /** @return array{nome: string, email: ?string, telefone: ?string}|null */
    public static function buscar(string $cpf): ?array
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) !== 11 || ! Esquema::temV011()) {
            return null;
        }
        $p = DB::table('pessoa')->where('cpf', $cpf)->whereNull('anonimizado_em')->first(['nome', 'email', 'telefone']);

        return $p ? ['nome' => $p->nome, 'email' => $p->email, 'telefone' => $p->telefone] : null;
    }
}
