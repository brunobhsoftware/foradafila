<?php

namespace App\Rules;

use App\Support\NomePessoa;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Exige nome e sobrenome (decisão do Bruno, 16/11/2026). */
class NomeCompleto implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (filled($value) && ! NomePessoa::completo((string) $value)) {
            $fail('Informe o nome completo (nome e sobrenome).');
        }
    }
}
