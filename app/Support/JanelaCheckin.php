<?php

namespace App\Support;

use App\Models\Evento;
use Illuminate\Support\Carbon;

/**
 * Quando o check-in de um evento está liberado (decisão do Bruno, 09/11/2026): só no DIA do evento, a partir da abertura dos
 * portões (o produtor pode abrir 1 ou 2 horas antes do início) — nunca antes da meia-noite do dia do início — até o término.
 */
class JanelaCheckin
{
    public const FUSO = 'America/Sao_Paulo';

    /** @return array{aberta: bool, abre: Carbon, fecha: Carbon, motivo: ?string} */
    public static function situacao(Evento $evento, ?Carbon $agora = null): array
    {
        $agora ??= now();
        $inicio = Carbon::parse($evento->data_inicio);
        $diaDoEvento = $inicio->copy()->setTimezone(self::FUSO)->startOfDay()->utc();
        $abre = Carbon::parse($evento->abertura_portoes ?? $inicio);
        if ($abre->lt($diaDoEvento)) {
            $abre = $diaDoEvento; // nunca antes do dia do evento
        }
        if ($abre->gt($inicio)) {
            $abre = $inicio->copy();
        }
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
