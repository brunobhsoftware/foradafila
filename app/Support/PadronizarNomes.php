<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige os nomes de pessoas já gravados (decisão do Bruno, 16/11/2026), com o mesmo algoritmo do cadastro (NomePessoa).
 * Administração → Atualizações do banco → "Padronizar nomes". Pode rodar quantas vezes quiser: só grava o que muda.
 */
class PadronizarNomes
{
    public const COLUNAS = [
        'usuario' => 'nome', 'comprador' => 'nome', 'participante' => 'nome', 'pessoa' => 'nome',
        'convidado' => 'nome', 'lista_email_contato' => 'nome', 'organizador' => 'representante_nome',
    ];

    /** Nomes de empresa (razão social e nome fantasia), com siglas ME/EPP/EIRELI/S/A em maiúsculas. */
    public const COLUNAS_EMPRESA = ['organizador' => ['razao_social', 'nome_fantasia']];

    /** @return array<string, int> registros alterados por tabela */
    public static function executar(): array
    {
        $alterados = [];
        $pares = [];
        foreach (self::COLUNAS as $t => $c) {
            $pares[] = [$t, $c];
        }
        foreach (self::COLUNAS_EMPRESA as $t => $cs) {
            foreach ($cs as $c) {
                $pares[] = [$t, $c];
            }
        }
        foreach ($pares as [$tabela, $coluna]) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, $coluna)) {
                continue;
            }
            $n = 0;
            DB::table($tabela)->whereNotNull($coluna)->select(['id', $coluna])->orderBy('id')
                ->chunkById(500, function ($linhas) use ($tabela, $coluna, &$n) {
                    foreach ($linhas as $l) {
                        $novo = in_array($coluna, ['razao_social', 'nome_fantasia'], true) ? NomePessoa::padronizarEmpresa($l->$coluna) : NomePessoa::padronizar($l->$coluna);
                        if ($novo !== '' && $novo !== $l->$coluna) {
                            DB::table($tabela)->where('id', $l->id)->update([$coluna => $novo]);
                            $n++;
                        }
                    }
                });
            $alterados[$tabela.'.'.$coluna] = $n;
        }

        return $alterados;
    }
}
