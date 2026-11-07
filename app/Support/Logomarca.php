<?php

namespace App\Support;

use App\Models\Organizador;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Logomarca da organização.
 *
 * Arquivo: logomarcas/<nome da organização>_<uuid da organização>.<extensão>
 *   ex.: logomarcas/estacao-slod_6f0c...9a.png  (nome em minúsculas, sem acentos nem espaços)
 * Disco "midia" (fora do public_html em produção), servida por /midia/logomarcas/<arquivo>.
 * organizador.logo_url guarda /midia/logomarcas/<arquivo>?v=<data do envio>.
 */
class Logomarca
{
    public const PASTA = 'logomarcas';

    public const LARGURA_RECOMENDADA = 136;

    public const ALTURA_RECOMENDADA = 136;

    public static function regras(): array
    {
        return ImagemEvento::regras();
    }

    public static function salvar(Organizador $org, UploadedFile $arquivo): string
    {
        $extensao = array_search($arquivo->getMimeType(), ImagemEvento::TIPOS, true) ?: 'png';
        $nome = Str::limit(Str::slug($org->nomeExibicao()) ?: 'organizacao', 60, '').'_'.$org->uuid.'.'.$extensao;

        self::remover($org);
        Storage::disk('midia')->putFileAs(self::PASTA, $arquivo, $nome);

        return '/midia/'.self::PASTA.'/'.$nome.'?v='.now()->timestamp;
    }

    /** Apaga qualquer logomarca anterior da organização (outro nome ou outra extensão). */
    public static function remover(Organizador $org): void
    {
        $disco = Storage::disk('midia');
        foreach ($disco->files(self::PASTA) as $arquivo) {
            if (str_contains($arquivo, '_'.$org->uuid.'.')) {
                $disco->delete($arquivo);
            }
        }
    }
}
