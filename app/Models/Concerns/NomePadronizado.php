<?php

namespace App\Models\Concerns;

use App\Support\NomePessoa;

/** Grava a coluna `nome` sempre padronizada (App\Support\NomePessoa). */
trait NomePadronizado
{
    public function setNomeAttribute($valor): void
    {
        $this->attributes['nome'] = $valor === null ? null : NomePessoa::padronizar((string) $valor);
    }
}
