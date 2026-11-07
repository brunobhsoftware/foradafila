<?php

namespace App\Models;

use App\Models\Concerns\PertenceAoOrganizador;
use Illuminate\Database\Eloquent\Model;

class Local extends Model
{
    use PertenceAoOrganizador;

    protected $table = 'local';

    public $timestamps = false;

    protected $fillable = ['nome', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'capacidade'];

    public function enderecoCurto(): string
    {
        return collect([$this->nome, $this->cidade ? $this->cidade.'/'.$this->uf : null])->filter()->implode(' · ');
    }
}
