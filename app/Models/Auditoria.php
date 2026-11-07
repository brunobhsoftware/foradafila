<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table = 'auditoria';

    public $timestamps = false;

    protected $fillable = ['organizador_id', 'usuario_id', 'admin_impersonando_id', 'impersonacao_id', 'acao', 'tabela', 'registro_id', 'antes', 'depois', 'ip'];

    protected function casts(): array
    {
        return ['antes' => 'array', 'depois' => 'array'];
    }
}
