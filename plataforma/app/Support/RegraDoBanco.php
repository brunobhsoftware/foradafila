<?php

namespace App\Support;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Os triggers do banco recusam operações com SQLSTATE 45000 e uma mensagem
 * (ex.: "Evento sem lote nao pode ser publicado."). Aqui a recusa vira erro de
 * formulário com o texto do banco, com acentos, em vez de uma tela de erro 500.
 */
class RegraDoBanco
{
    private const ACENTOS = [
        'nao ' => 'não ', 'esta ' => 'está ', 'debito' => 'débito', 'responsabilidade' => 'responsabilidade',
        'preco' => 'preço', 'producao' => 'produção', 'Homologacao' => 'Homologação', 'obrigatorios' => 'obrigatórios',
        'so pode' => 'só pode', 'Conta so' => 'Conta só', 'ate ' => 'até ',
    ];

    public static function executar(Closure $operacao, string $campo = 'regra')
    {
        try {
            return $operacao();
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '45000') {
                throw ValidationException::withMessages([$campo => strtr($e->errorInfo[2] ?? 'Operação recusada pelo banco.', self::ACENTOS)]);
            }
            throw $e;
        }
    }
}
