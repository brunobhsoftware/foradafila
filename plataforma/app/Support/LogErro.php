<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LogErro
{
    public static function registrar(string $contexto, \Throwable $e, ?int $organizadorId = null, ?array $extras = null): void
    {
        try {
            if (! Schema::hasTable('log_erro')) {
                return;
            }
            DB::table('log_erro')->insert([
                'organizador_id' => $organizadorId,
                'contexto' => mb_substr($contexto, 0, 100),
                'mensagem' => mb_substr($e->getMessage(), 0, 65000),
                'detalhes' => mb_substr(json_encode(array_filter([
                    'classe' => get_class($e),
                    'arquivo' => basename($e->getFile()).':'.$e->getLine(),
                    'extras' => $extras,
                    'trace' => mb_substr($e->getTraceAsString(), 0, 2000),
                ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 65000),
                'url' => mb_substr((string) request()?->fullUrl(), 0, 500) ?: null,
                'ip' => request()?->ip(),
                'usuario_id' => auth()->id(),
                'comprador_id' => auth('comprador')->id(),
            ]);
        } catch (\Throwable) {
            // Nunca interrompe a operação original.
        }
    }
}
