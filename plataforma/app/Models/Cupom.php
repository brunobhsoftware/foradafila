<?php

namespace App\Models;

use App\Models\Concerns\PertenceAoOrganizador;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cupom extends Model
{
    use PertenceAoOrganizador;

    protected $table = 'cupom';

    public $timestamps = false;

    protected $fillable = [
        'evento_id', 'codigo', 'descricao', 'tipo_desconto', 'valor', 'aplica_em', 'valor_minimo_pedido',
        'max_usos', 'max_usos_cpf', 'valido_de', 'valido_ate', 'ativo', 'criado_por',
    ];

    protected function casts(): array
    {
        return ['valido_de' => 'datetime', 'valido_ate' => 'datetime', 'valor' => 'decimal:2', 'ativo' => 'boolean'];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function lotes(): BelongsToMany
    {
        return $this->belongsToMany(Lote::class, 'cupom_lote', 'cupom_id', 'lote_id');
    }

    public function ehDivulgacao(): bool
    {
        return $this->tipo_desconto === 'divulgacao';
    }

    public function descontoTexto(): string
    {
        if ($this->ehDivulgacao()) {
            return 'Divulgação';
        }

        return $this->tipo_desconto === 'percentual' ? numero($this->valor, 0).'%' : moeda($this->valor);
    }

    /** Situação para a lista: ativo, esgotado, expirado, agendado, desativado. */
    public function situacao(): string
    {
        if (! $this->ativo) {
            return 'desativado';
        }
        if ($this->ehDivulgacao()) {
            return 'ativo'; // sem limites nem validade
        }
        if ($this->max_usos !== null && $this->usos >= $this->max_usos) {
            return 'esgotado';
        }
        if ($this->valido_ate && $this->valido_ate->isPast()) {
            return 'expirado';
        }
        if ($this->valido_de && $this->valido_de->isFuture()) {
            return 'agendado';
        }

        return 'ativo';
    }

    /**
     * Desconto do cupom para um ingresso/pedido (usado pela venda paga quando ela entrar).
     * Retorna null se o cupom não vale para esse caso.
     */
    public function desconto(float $valor, ?int $loteId = null, ?float $subtotalPedido = null): ?float
    {
        if ($this->situacao() !== 'ativo') {
            return null;
        }
        if ($this->ehDivulgacao()) {
            return 0.0;
        }
        $lotes = \Illuminate\Support\Facades\DB::table('cupom_lote')->where('cupom_id', $this->id)->pluck('lote_id');
        if ($loteId && $lotes->isNotEmpty() && ! $lotes->contains($loteId)) {
            return null;
        }
        if ($this->valor_minimo_pedido !== null && ($subtotalPedido ?? $valor) < (float) $this->valor_minimo_pedido) {
            return null;
        }
        $d = $this->tipo_desconto === 'percentual' ? $valor * (float) $this->valor / 100 : (float) $this->valor;

        return round(min($d, $valor), 2);
    }
}
