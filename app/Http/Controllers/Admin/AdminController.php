<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Pagamentos\MercadoPago;
use App\Support\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Administração da plataforma (equipe BH Software). */
class AdminController extends Controller
{
    public function inicio()
    {
        return view('admin.inicio', [
            'numeros' => (object) [
                'organizadores' => DB::table('organizador')->count(),
                'eventos' => DB::table('evento')->where('status', 'publicado')->count(),
                'contas' => DB::table('conta_pagamento')->whereNotIn('status', ['pendente', 'revogada'])->count(),
            ],
            'mpConfigurado' => MercadoPago::configurado(),
        ]);
    }

    public function mercadoPago()
    {
        return view('admin.mercadopago', ['credenciais' => MercadoPago::situacaoCredenciais(), 'configurado' => MercadoPago::configurado()]);
    }

    public function salvarMercadoPago(Request $request)
    {
        $dados = $request->validate([
            'client_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'client_secret' => ['nullable', 'string', 'max:200', 'regex:/^\S+$/'],
            'webhook_secret' => ['nullable', 'string', 'max:200', 'regex:/^\S+$/'],
        ], [
            'client_id.regex' => 'O Client ID tem só letras, números, "-" e "_".',
            'client_secret.regex' => 'O Client Secret não pode ter espaços.',
            'webhook_secret.regex' => 'A assinatura secreta não pode ter espaços.',
        ], ['client_id' => 'Client ID', 'client_secret' => 'Client Secret', 'webhook_secret' => 'assinatura secreta']);

        $alteradas = array_keys(array_filter($dados, 'filled'));
        if (! $alteradas) {
            return back()->withErrors(['client_id' => 'Preencha pelo menos um campo. Campos em branco mantêm o valor atual.']);
        }
        MercadoPago::salvarCredenciais($dados);
        Auditoria::registrar('plataforma.mercadopago_credenciais', $request->user(), null, ['alteradas' => $alteradas]);

        return redirect()->route('admin.mercadopago')->with('ok', 'Chaves do Mercado Pago salvas. Os segredos ficam criptografados no banco.');
    }
}
