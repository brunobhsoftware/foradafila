<?php

namespace App\Http\Controllers;

use App\Support\ImagemEvento;
use App\Support\Logomarca;
use Illuminate\Support\Facades\Storage;

/** Entrega as imagens guardadas fora do public_html (disco "midia"). */
class MidiaController extends Controller
{
    public function __invoke(string $pasta, string $arquivo)
    {
        abort_unless(in_array($pasta, [ImagemEvento::PASTA, Logomarca::PASTA], true), 404);
        $ext = pathinfo($arquivo, PATHINFO_EXTENSION);
        $caminho = $pasta.'/'.$arquivo;
        abort_unless(isset(ImagemEvento::TIPOS[$ext]) && Storage::disk('midia')->exists($caminho), 404);

        return response()->file(Storage::disk('midia')->path($caminho), [
            'Content-Type' => ImagemEvento::TIPOS[$ext],
            'Cache-Control' => 'public, max-age=31536000, immutable', // o endereço muda (?v=) quando a imagem é trocada
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
