<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organizador;
use Illuminate\Http\Request;

/** Quem trabalha para mais de um organizador escolhe em qual vai entrar. */
class OrganizacaoSelecaoController extends Controller
{
    public function index(Request $request)
    {
        $vinculos = $request->user()->vinculosAtivos()->with('perfil')->get();
        $organizadores = Organizador::whereIn('id', $vinculos->pluck('organizador_id'))->get()->keyBy('id');

        return view('auth.organizacoes', compact('vinculos', 'organizadores'));
    }

    public function store(Request $request)
    {
        $id = (int) $request->input('organizador_id');
        abort_unless($request->user()->vinculosAtivos()->where('organizador_id', $id)->exists(), 403);

        $request->session()->put('organizador_id', $id);

        return redirect()->route('painel');
    }
}
