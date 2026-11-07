<?php

namespace App\Models;

use App\Models\Concerns\PertenceAoOrganizador;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Setor do evento (Pista, VIP, Camarote). */
class IngressoTipo extends Model
{
    use PertenceAoOrganizador;

    protected $table = 'ingresso_tipo';

    public $timestamps = false;

    protected $fillable = ['evento_id', 'nome', 'descricao', 'capacidade', 'ordem', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class)->orderBy('ordem')->orderBy('id');
    }
}
