<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Modelos de mensagem (tabela modelo_mensagem). Hierarquia: modelo do evento →
 * do organizador → padrão do sistema; vale o mais específico que estiver ativo.
 * Variáveis no formato {{nome}}.
 */
class ModeloMensagem
{
    /** @return array{assunto: string, texto: string, modelo_id: ?int}|null */
    public static function renderizar(string $tipo, ?int $organizadorId, ?int $eventoId, array $variaveis): ?array
    {
        $modelo = DB::table('modelo_mensagem')->where('tipo', $tipo)->where('canal', 'email')->where('ativo', 1)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('organizador_id', $organizadorId)->where('evento_id', $eventoId))
                ->orWhere(fn ($q) => $q->where('organizador_id', $organizadorId)->whereNull('evento_id'))
                ->orWhere(fn ($q) => $q->whereNull('organizador_id')->whereNull('evento_id')))
            ->orderByRaw('evento_id IS NULL, organizador_id IS NULL')
            ->first();

        if (! $modelo) {
            return null;
        }

        $trocar = fn (?string $t) => preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => (string) ($variaveis[$m[1]] ?? ''), (string) $t);
        $texto = $trocar($modelo->corpo_texto);

        // O modelo padrão do V001 cita "em anexo", mas o ingresso não vai anexado (fica em Meus ingressos).
        // Corrigido no banco pelo V003; até lá o texto é ajustado aqui.
        $texto = str_replace('Seus ingressos estão em anexo e em ', 'Seus ingressos estão em ', $texto);

        return ['assunto' => $trocar($modelo->assunto), 'texto' => $texto, 'modelo_id' => $modelo->id];
    }
}
