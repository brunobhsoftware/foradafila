<?php

namespace App\Http\Controllers;

use App\Models\Termo;

class SiteController extends Controller
{
    public function inicio()
    {
        return view('site.inicio');
    }

    public function termo(string $tipo)
    {
        $termo = Termo::vigente()->where('tipo', $tipo)->orderByDesc('vigente_desde')->firstOrFail();

        return view('site.termo', compact('termo'));
    }
}
