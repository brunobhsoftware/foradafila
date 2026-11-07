<?php

namespace App\Models;

use App\Models\Concerns\PertenceAoOrganizador;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizadorPlano extends Model
{
    use PertenceAoOrganizador;

    protected $table = 'organizador_plano';

    public $timestamps = false;

    protected $fillable = ['organizador_id', 'plano_id', 'inicio', 'fim'];

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }
}
