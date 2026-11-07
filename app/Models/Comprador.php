<?php

namespace App\Models;

use App\Models\Concerns\TemUuid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** Quem compra. Conta própria ("Meus ingressos"), separada do usuário produtor. */
class Comprador extends Authenticatable
{
    use TemUuid;

    protected $table = 'comprador';

    public $timestamps = false;

    protected $rememberTokenName = '';

    protected $fillable = ['nome', 'email', 'cpf', 'telefone', 'data_nascimento', 'senha_hash', 'email_verificado_em'];

    protected $hidden = ['senha_hash'];

    protected function casts(): array
    {
        return ['email_verificado_em' => 'datetime', 'anonimizado_em' => 'datetime', 'data_nascimento' => 'date'];
    }

    public function getAuthPasswordName(): string
    {
        return 'senha_hash';
    }

    public function getAuthPassword(): ?string
    {
        return $this->senha_hash;
    }

    /** Pedidos de todos os organizadores: a área do comprador não usa o filtro por organizador. */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class)->withoutGlobalScope('organizador');
    }

    public function primeiroNome(): string
    {
        return strtok($this->nome, ' ') ?: $this->nome;
    }
}
