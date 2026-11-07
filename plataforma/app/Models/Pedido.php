<?php

namespace App\Models;

use App\Models\Concerns\PertenceAoOrganizador;
use App\Models\Concerns\TemUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Pedido extends Model
{
    use PertenceAoOrganizador, TemUuid;

    protected $table = 'pedido';

    public $timestamps = false;

    protected $fillable = [
        'codigo', 'organizador_id', 'evento_id', 'comprador_id', 'origem', 'chave_idempotencia', 'status',
        'valor_bruto', 'valor_desconto', 'valor_total', 'metodo_pagamento', 'ip', 'expira_em', 'pago_em', 'cancelado_em',
        'utm_source', 'utm_medium', 'utm_campaign',
    ];

    protected function casts(): array
    {
        return ['expira_em' => 'datetime', 'pago_em' => 'datetime', 'cancelado_em' => 'datetime', 'valor_total' => 'decimal:2'];
    }

    public const STATUS = [
        'reservado' => 'Reservado', 'aguardando_pagamento' => 'Aguardando pagamento', 'pago' => 'Confirmado',
        'expirado' => 'Expirado', 'recusado' => 'Pagamento recusado', 'cancelado' => 'Cancelado',
        'reembolsado' => 'Reembolsado', 'reembolso_parcial' => 'Reembolso parcial', 'contestado' => 'Em contestação',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class)->withoutGlobalScope('organizador');
    }

    public function organizadorPublico(): BelongsTo
    {
        return $this->belongsTo(Organizador::class, 'organizador_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function ingressos(): HasMany
    {
        return $this->hasMany(Ingresso::class)->withoutGlobalScope('organizador')->orderBy('numero');
    }

    public function ehGratuito(): bool
    {
        return (float) $this->valor_total === 0.0;
    }

    /** Número exibido ao comprador, ex.: FF7K2M9Q (sem 0/O/1/I para não confundir). */
    public static function novoCodigo(): string
    {
        do {
            $codigo = 'FF'.collect(range(1, 6))->map(fn () => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 31)])->implode('');
        } while (static::withoutGlobalScope('organizador')->where('codigo', $codigo)->exists());

        return $codigo;
    }
}
