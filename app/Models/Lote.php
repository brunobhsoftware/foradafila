<?php

namespace App\Models;

use App\Models\Concerns\PertenceAoOrganizador;
use App\Models\Concerns\TemUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lote extends Model
{
    use PertenceAoOrganizador, TemUuid;

    protected $table = 'lote';

    public $timestamps = false;

    protected $fillable = [
        'exibir',
        'evento_id', 'ingresso_tipo_id', 'lote_anterior_id', 'nome', 'modalidade', 'preco', 'quantidade',
        'min_por_pedido', 'max_por_pedido', 'venda_inicio', 'venda_fim', 'virada', 'visibilidade', 'status', 'ordem',
    ];

    protected function casts(): array
    {
        return ['venda_inicio' => 'datetime', 'venda_fim' => 'datetime', 'preco' => 'decimal:2'];
    }

    public const MODALIDADES = ['inteira' => 'Inteira', 'meia' => 'Meia-entrada', 'cortesia' => 'Cortesia'];

    public const VIRADAS = [
        'esgotar_ou_data' => 'Ao esgotar ou na data final',
        'ao_esgotar' => 'Ao esgotar',
        'por_data' => 'Na data final',
        'manual' => 'Manual',
    ];

    // "oculto" é o ingresso emitido manualmente pela equipe (não aparece na página do evento);
    // "convidados" é vendido só por link enviado por e-mail ou WhatsApp.
    public const VISIBILIDADES = [
        'publico' => 'Para todo o público',
        'convidados' => 'Restrito a convidados',
        'oculto' => 'Para ser adicionado manualmente',
    ];

    public const VISIBILIDADES_AJUDA = [
        'publico' => 'Aparece na página do evento para qualquer pessoa.',
        'convidados' => 'Não aparece na página: o link é enviado por e-mail ou WhatsApp.',
        'oculto' => 'Não é vendido: a equipe adiciona o participante pelo painel.',
    ];

    public const STATUS = [
        'agendado' => 'Agendado', 'ativo' => 'À venda', 'pausado' => 'Desativado',
        'esgotado' => 'Esgotado', 'encerrado' => 'Encerrado',
    ];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(IngressoTipo::class, 'ingresso_tipo_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function temMovimento(): bool
    {
        return ($this->vendido + $this->reservado) > 0;
    }
}
