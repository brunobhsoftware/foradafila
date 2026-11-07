<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Perfil = conjunto de permissões. organizador_id NULL = perfil padrão do sistema. */
class PerfilAcesso extends Model
{
    protected $table = 'perfil_acesso';

    public $timestamps = false;

    protected $fillable = ['organizador_id', 'codigo', 'nome', 'descricao', 'editavel'];

    protected function casts(): array
    {
        return ['editavel' => 'boolean'];
    }

    /**
     * O perfil padrão de código "dono" aparece como "Produtor" (pedido do Bruno, 07/11/2026).
     * O código interno continua "dono": permissões e regras (último Dono ativo etc.) não mudam.
     */
    protected function nome(): Attribute
    {
        return Attribute::get(fn ($valor, $atributos) => ($atributos['organizador_id'] ?? null) === null && ($atributos['codigo'] ?? null) === 'dono'
            ? 'Produtor' : $valor);
    }

    protected function descricao(): Attribute
    {
        return Attribute::get(fn ($valor, $atributos) => ($atributos['organizador_id'] ?? null) === null && ($atributos['codigo'] ?? null) === 'dono'
            ? 'Acesso total; responde pela organização' : $valor);
    }

    public function permissoes(): BelongsToMany
    {
        return $this->belongsToMany(Permissao::class, 'perfil_permissao', 'perfil_id', 'permissao_id');
    }

    /** Perfis que um organizador pode usar: os do sistema e os dele. */
    public function scopeDisponiveisPara(Builder $query, int $organizadorId): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('organizador_id')->orWhere('organizador_id', $organizadorId))
            ->orderByRaw('organizador_id IS NOT NULL')->orderBy('id');
    }

    public function ehDoSistema(): bool
    {
        return $this->organizador_id === null;
    }

    public static function dono(): self
    {
        return static::whereNull('organizador_id')->where('codigo', 'dono')->firstOrFail();
    }
}
