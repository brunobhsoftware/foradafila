<?php

namespace App\Models;

use App\Models\Concerns\PertenceAoOrganizador;
use Illuminate\Database\Eloquent\Model;

/** Titular do ingresso. Só as colunas que o fluxo atual preenche estão liberadas. */
class Participante extends Model
{
    use PertenceAoOrganizador;

    protected $table = 'participante';

    public $timestamps = false;

    protected $fillable = ['ingresso_id', 'organizador_id', 'evento_id', 'nome', 'cpf', 'email', 'telefone', 'mesmo_que_comprador'];
}
