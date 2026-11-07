<?php

namespace App\Rules;

use App\Support\Documento;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Valida CPF (PF) ou CNPJ (PJ) pelos dígitos verificadores. */
class DocumentoValido implements ValidationRule
{
    public function __construct(private ?string $tipoPessoa) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ok = $this->tipoPessoa === 'PJ' ? Documento::cnpjValido($value) : Documento::cpfValido($value);

        if (! $ok) {
            $fail($this->tipoPessoa === 'PJ' ? 'CNPJ inválido.' : 'CPF inválido.');
        }
    }
}
