<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Aplica pela Administração os scripts versionados do banco (cópias de banco/V0xx em database/atualizacoes),
 * para não depender do phpMyAdmin. Os scripts são idempotentes; cada aplicação fica registrada em
 * parametro_sistema (banco:V0xx) e na auditoria. Só scripts sem DELIMITER/triggers.
 */
class AtualizacaoBanco
{
    public static function pasta(): string
    {
        return base_path('database/atualizacoes');
    }

    /** @return array<int, array{arquivo: string, versao: string, descricao: string, aplicado_em: ?string}> */
    public static function lista(): array
    {
        $aplicados = DB::table('parametro_sistema')->where('chave', 'like', 'banco:%')->pluck('valor', 'chave');

        return collect(glob(self::pasta().'/V*.sql') ?: [])->sort()->map(function ($caminho) use ($aplicados) {
            $arquivo = basename($caminho);
            $versao = strtok($arquivo, '_');
            $conteudo = (string) file_get_contents($caminho);
            preg_match('/^--\s*Fora da Fila\s*—\s*V\d+:\s*(.+)$/mu', $conteudo, $m);

            return ['arquivo' => $arquivo, 'versao' => $versao, 'descricao' => trim($m[1] ?? $arquivo),
                'aplicado_em' => $aplicados['banco:'.$versao] ?? null];
        })->values()->all();
    }

    /** Executa o script, comando por comando. Retorna quantos comandos rodaram. */
    public static function aplicar(string $arquivo): int
    {
        abort_unless(preg_match('/^V\d{3}__[a-z0-9_]+\.sql$/', $arquivo), 404);
        $caminho = self::pasta().'/'.$arquivo;
        abort_unless(is_file($caminho), 404);
        $sql = (string) file_get_contents($caminho);
        abort_if(stripos($sql, 'DELIMITER') !== false, 422, 'Script com DELIMITER precisa ser importado no phpMyAdmin.');

        $n = 0;
        foreach (self::comandos($sql) as $comando) {
            // As consultas de conferência (SELECT/SHOW) do fim do script não alteram nada: ficam de fora.
            if (preg_match('/^\s*(SELECT|SHOW)\b/i', $comando)) {
                continue;
            }
            DB::unprepared($comando);
            $n++;
        }
        $versao = strtok($arquivo, '_');
        DB::table('parametro_sistema')->updateOrInsert(['chave' => 'banco:'.$versao],
            ['valor' => now('UTC')->format('Y-m-d H:i:s'), 'descricao' => 'Atualização do banco aplicada pela Administração: '.$arquivo]);

        return $n;
    }

    /** Separa os comandos por ";" fora de aspas e tira os comentários de linha. */
    public static function comandos(string $sql): array
    {
        $comandos = []; $atual = ''; $aspas = null; $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($aspas === null && $ch === '-' && ($sql[$i + 1] ?? '') === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                $atual .= "\n";
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                if ($aspas === null) {
                    $aspas = $ch;
                } elseif ($aspas === $ch && ($sql[$i - 1] ?? '') !== '\\') {
                    $aspas = null;
                }
            }
            if ($ch === ';' && $aspas === null) {
                if (trim($atual) !== '') {
                    $comandos[] = trim($atual);
                }
                $atual = '';
                continue;
            }
            $atual .= $ch;
        }
        if (trim($atual) !== '') {
            $comandos[] = trim($atual);
        }

        return $comandos;
    }
}
