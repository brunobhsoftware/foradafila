<?php

namespace App\Support;

use App\Models\Evento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Imagem de divulgação do evento.
 *
 * Arquivo: eventos/<id do evento>_<uuid do evento>.<extensão>  (ex.: eventos/12_9b1f...e3.jpg)
 * Disco "midia" (config/filesystems.php): fora do public_html em produção.
 * Endereço gravado em evento.imagem_capa_url: /midia/eventos/<arquivo>?v=<data do envio>,
 * para o navegador não mostrar a imagem antiga depois de uma troca.
 */
class ImagemEvento
{
    public const PASTA = 'eventos';

    public const TIPOS = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];

    public const TAMANHO_MAX_KB = 2048;

    public const LARGURA_RECOMENDADA = 1600;

    public const ALTURA_RECOMENDADA = 838;

    /** Regras de validação do campo (Laravel confere o conteúdo real do arquivo, não só a extensão). */
    public static function regras(): array
    {
        return ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,gif', 'mimetypes:image/jpeg,image/png,image/gif', 'max:'.self::TAMANHO_MAX_KB];
    }

    public static function salvar(Evento $evento, UploadedFile $arquivo): string
    {
        $extensao = array_search($arquivo->getMimeType(), self::TIPOS, true) ?: 'jpg';
        $nome = $evento->id.'_'.$evento->uuid.'.'.$extensao;

        self::removerArquivos($evento, exceto: $nome);
        Storage::disk('midia')->putFileAs(self::PASTA, $arquivo, $nome);

        return '/midia/'.self::PASTA.'/'.$nome.'?v='.now()->timestamp;
    }

    public static function remover(Evento $evento): void
    {
        self::removerArquivos($evento);
    }

    /** Apaga as versões do evento em outras extensões (ex.: trocou PNG por JPEG). */
    private static function removerArquivos(Evento $evento, ?string $exceto = null): void
    {
        foreach (array_keys(self::TIPOS) as $ext) {
            $nome = $evento->id.'_'.$evento->uuid.'.'.$ext;
            if ($nome !== $exceto) {
                Storage::disk('midia')->delete(self::PASTA.'/'.$nome);
            }
        }
    }
}
