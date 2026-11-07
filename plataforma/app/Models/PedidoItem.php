<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoItem extends Model
{
    protected $table = 'pedido_item';

    public $timestamps = false;

    protected $fillable = ['pedido_id', 'lote_id', 'quantidade', 'preco_unitario', 'desconto_unitario', 'subtotal'];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class)->withoutGlobalScope('organizador');
    }
}
