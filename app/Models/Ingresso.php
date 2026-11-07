<?php

namespace App\Models;

use App\Models\Concerns\PertenceAoOrganizador;
use App\Models\Concerns\TemUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ingresso extends Model
{
    use PertenceAoOrganizador, TemUuid;

    protected $table = 'ingresso';

    public $timestamps = false;

    protected $fillable = [
        'organizador_id', 'evento_id', 'pedido_id', 'pedido_item_id', 'lote_id', 'ingresso_tipo_id',
        'numero', 'codigo_qr', 'valor_pago', 'status', 'cancelado_em',
    ];

    protected function casts(): array
    {
        return ['primeiro_checkin_em' => 'datetime', 'cancelado_em' => 'datetime', 'valor_pago' => 'decimal:2'];
    }

    public const STATUS = [
        'valido' => 'Válido', 'utilizado' => 'Utilizado', 'cancelado' => 'Cancelado',
        'reembolsado' => 'Reembolsado', 'transferido' => 'Transferido',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class)->withoutGlobalScope('organizador');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class)->withoutGlobalScope('organizador');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class)->withoutGlobalScope('organizador');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(IngressoTipo::class, 'ingresso_tipo_id')->withoutGlobalScope('organizador');
    }

    public function participante(): HasOne
    {
        return $this->hasOne(Participante::class)->withoutGlobalScope('organizador');
    }

    /** Número exibido: FF-<evento com 4 dígitos>-<número com 6 dígitos>. */
    public function numeroExibicao(): string
    {
        return sprintf('FF-%04d-%06d', $this->evento_id, $this->numero);
    }

    /** 256 bits aleatórios em base64url (43 caracteres): não é sequencial nem derivável do número. */
    public static function novoCodigoQr(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Conteúdo do QR Code: código + assinatura HMAC (regras, seção 9, item 6).
     * O check-in confere a assinatura antes de consultar o banco (inclusive offline).
     */
    public function conteudoQr(): string
    {
        $assinatura = substr(hash_hmac('sha256', $this->codigo_qr, (string) config('app.key')), 0, 16);

        return 'FF1.'.$this->codigo_qr.'.'.$assinatura;
    }
}
