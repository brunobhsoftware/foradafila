<?php

namespace App\Models;

use App\Models\Concerns\TemUuid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    use TemUuid;

    protected $table = 'usuario';

    public $timestamps = false;

    /** A tabela não tem remember_token: "lembrar de mim" fica desligado. */
    protected $rememberTokenName = '';

    protected $fillable = ['nome', 'email', 'telefone', 'senha_hash', 'email_verificado_em', 'ativo'];

    protected $hidden = ['senha_hash', 'totp_segredo_cript'];

    protected function casts(): array
    {
        return [
            'email_verificado_em' => 'datetime',
            'bloqueado_ate' => 'datetime',
            'ultimo_login_em' => 'datetime',
            'ativo' => 'boolean',
            'admin_plataforma' => 'boolean',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'senha_hash';
    }

    public function getAuthPassword(): ?string
    {
        return $this->senha_hash;
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(OrganizadorUsuario::class)->withoutGlobalScope('organizador');
    }

    public function vinculosAtivos(): HasMany
    {
        return $this->vinculos()->where('status', 'ativo');
    }

    public function primeiroNome(): string
    {
        return strtok($this->nome, ' ') ?: $this->nome;
    }
}
