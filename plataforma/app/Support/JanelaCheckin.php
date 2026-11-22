<?php

namespace App\Support;

use App\Models\Evento;
use Illuminate\Support\Carbon;

/**
 * Quando o check-in de um evento está liberado: a partir da abertura dos portões (ou até 5 horas antes do início,
 * decisão do Bruno, 22/11/2026) até o término.
 */
class JanelaCheckin
{
    public const FUSO = 'America/Sao_Paulo';

    public const ANTECEDENCIA_HORAS = 5;

    /** @return array{aberta: bool, abre: Carbon, fecha: Carbon, motivo: ?string} */
    public static function situacao(Evento $evento, ?Carbon $agora = null): array
    {
        $agora ??= now();
        $inicio = Carbon::parse($evento->data_inicio);
        $limiteMinimo = $inicio->copy()->subHours(self::ANTECEDENCIA_HORAS);
        $abre = $limiteMinimo;
        $fecha = Carbon::parse($evento->data_fim ?? $inicio->copy()->endOfDay());
        $motivo = match (true) {
            ! in_array($evento->status, ['publicado', 'encerrado'], true) => 'O evento não está publicado.',
            $agora->lt($abre) => 'O check-in abre em '.$abre->copy()->setTimezone(self::FUSO)->format('d/m \à\s H:i').'.',
            $agora->gt($fecha) => 'O evento terminou em '.$fecha->copy()->setTimezone(self::FUSO)->format('d/m \à\s H:i').'.',
            default => null,
        };

        return ['aberta' => $motivo === null, 'abre' => $abre, 'fecha' => $fecha, 'motivo' => $motivo];
    }
}
