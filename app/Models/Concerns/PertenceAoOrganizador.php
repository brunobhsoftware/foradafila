<?php

namespace App\Models\Concerns;

use App\Models\Organizador;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-organizador: toda consulta filtra por organizador_id do Tenant atual
 * e todo registro novo recebe esse organizador_id.
 *
 * Falha fechada: sem organizador definido, a consulta não retorna nada.
 * Para ler fora de um organizador (ex.: login), use withoutGlobalScope('organizador').
 */
trait PertenceAoOrganizador
{
    public static function bootPertenceAoOrganizador(): void
    {
        static::addGlobalScope('organizador', function (Builder $query) {
            $id = app(Tenant::class)->id();
            $coluna = $query->getModel()->getTable().'.organizador_id';
            $id ? $query->where($coluna, $id) : $query->whereRaw('1 = 0');
        });

        static::creating(function ($model) {
            if (empty($model->organizador_id)) {
                $model->organizador_id = app(Tenant::class)->id();
            }
        });
    }

    public function organizador(): BelongsTo
    {
        return $this->belongsTo(Organizador::class);
    }
}
