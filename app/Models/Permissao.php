<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permissao extends Model
{
    protected $table = 'permissao';

    public $timestamps = false;

    public const MODULOS = [
        'dashboard' => 'Painel',
        'organizador' => 'Organização',
        'evento' => 'Eventos',
        'divulgacao' => 'Divulgação',
        'convidados' => 'Convidados',
        'vendas' => 'Vendas e participantes',
        'checkin' => 'Check-in',
        'financeiro' => 'Financeiro',
        'equipe' => 'Equipe',
    ];
}
