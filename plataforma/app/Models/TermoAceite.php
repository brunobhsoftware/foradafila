<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Prova do aceite: nunca é apagado nem alterado. */
class TermoAceite extends Model
{
    protected $table = 'termo_aceite';

    public $timestamps = false;

    protected $fillable = ['termo_id', 'organizador_id', 'usuario_id', 'comprador_id', 'ip', 'user_agent'];
}
