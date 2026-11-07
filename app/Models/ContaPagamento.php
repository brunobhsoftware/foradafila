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

    protected $hidden = ['access_token_cript', 'refresh_token_cript'];
}
