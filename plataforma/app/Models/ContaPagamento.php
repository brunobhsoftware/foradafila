<?php

namespace App\Models;

use App\Models\Concerns\PertenceAoOrganizador;
use App\Models\Concerns\TemUuid;
use Illuminate\Database\Eloquent\Model;

class ContaPagamento extends Model
{
    use PertenceAoOrganizador, TemUuid;

    protected $table = 'conta_pagamento';

    public $timestamps = false;

    protected $guarded = ['id', 'organizador_id', 'uuid'];

    protected $hidden = ['access_token_cript', 'refresh_token_cript'];

    public const STATUS = [
        'pendente' => 'Pendente', 'conectada' => 'Conectada', 'em_homologacao' => 'Em homologação',
        'homologada' => 'Homologada', 'ativa' => 'Ativa', 'suspensa' => 'Suspensa', 'revogada' => 'Desconectada',
    ];

    protected function casts(): array
    {
        return ['token_expira_em' => 'datetime', 'conectado_em' => 'datetime', 'desconectado_em' => 'datetime', 'ultimo_erro_em' => 'datetime'];
    }

    /** Token de acesso do organizador (guardado criptografado com a APP_KEY). */
    public function accessToken(): ?string
    {
        return $this->access_token_cript ? decrypt($this->access_token_cript, false) : null;
    }

    public function conectada(): bool
    {
        return ! in_array($this->status, ['pendente', 'revogada'], true) && $this->access_token_cript;
    }
}
