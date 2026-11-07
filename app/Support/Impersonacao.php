<?php

namespace App\Support;

use App\Models\Organizador;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * "Assumir conta": o administrador da plataforma entra no painel de uma empresa.
 * O contexto fica na sessão (quem é o administrador, qual empresa, qual registro de log);
 * toda ação gravada em auditoria nesse período leva admin_impersonando_id e impersonacao_id.
 */
class Impersonacao
{
    private const CHAVE = 'impersonacao';

    public static function iniciar(Usuario $admin, Organizador $org, ?string $motivo = null): void
    {
        self::encerrar();
        $logId = DB::table('impersonacao_log')->insertGetId([
            'admin_id' => $admin->id, 'organizador_id' => $org->id, 'motivo' => $motivo ? mb_substr($motivo, 0, 255) : null,
            'inicio' => now('UTC'), 'ip' => request()->ip(), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
        ]);
        session([self::CHAVE => ['admin_id' => $admin->id, 'organizador_id' => $org->id, 'log_id' => $logId, 'nome' => $org->nomeExibicao()],
            'organizador_id' => $org->id]);
    }

    /** Dados da sessão de impersonação ativa (só vale para o próprio administrador logado). */
    public static function ativa(): ?array
    {
        $dados = session(self::CHAVE);
        $usuario = auth()->user();

        return $dados && $usuario && $usuario->admin_plataforma && (int) $dados['admin_id'] === (int) $usuario->id ? $dados : null;
    }

    public static function encerrar(): ?array
    {
        $dados = session(self::CHAVE);
        if ($dados) {
            DB::table('impersonacao_log')->where('id', $dados['log_id'])->whereNull('fim')->update(['fim' => now('UTC')]);
            session()->forget([self::CHAVE, 'organizador_id']);
        }

        return $dados;
    }

    /** Usado pela Auditoria: marca a ação e conta no log. */
    public static function marcarAcao(): array
    {
        $a = self::ativa();
        if (! $a) {
            return [];
        }
        DB::table('impersonacao_log')->where('id', $a['log_id'])->increment('acoes');

        return ['admin_impersonando_id' => $a['admin_id'], 'impersonacao_id' => $a['log_id']];
    }
}
