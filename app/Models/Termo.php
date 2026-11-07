<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Termo extends Model
{
    protected $table = 'termo';

    public $timestamps = false;

    /** Termos que o organizador aceita no cadastro. */
    public const DO_ORGANIZADOR = ['uso_organizador', 'responsabilidade_organizador', 'privacidade'];

    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('vigente', 1);
    }
}
