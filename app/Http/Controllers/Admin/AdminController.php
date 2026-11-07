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

    /** Pagamento de teste (Checkout Pro) na conta da própria aplicação, para validar a integração de ponta a ponta. */
    public function testarPagamento(Request $request)
    {
        $valor = (float) $request->validate(['valor' => ['required', 'numeric', 'min:1', 'max:100']], [], ['valor' => 'valor'])['valor'];
        if (! MercadoPago::configurado()) {
            return back()->withErrors(['valor' => 'Cadastre primeiro o Client ID e o Client Secret.']);
        }
        $referencia = 'teste-'.\Illuminate\Support\Str::uuid();
        try {
            $pref = MercadoPago::criarPreferencia(MercadoPago::tokenAplicacao(), [
                'items' => [['id' => 'teste-ff', 'title' => 'Teste de integração Fora da Fila', 'quantity' => 1, 'unit_price' => round($valor, 2), 'currency_id' => 'BRL']],
                'external_reference' => $referencia,
                'back_urls' => array_fill_keys(['success', 'failure', 'pending'], route('admin.mercadopago.teste.retorno')),
                'auto_return' => 'approved',
                'notification_url' => url(config('pagamentos.mercadopago.webhook')),
                'statement_descriptor' => 'FORADAFILA',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Cache::forget('mp_token_aplicacao_'.md5((string) MercadoPago::credencial('client_id')));

            return back()->withErrors(['valor' => $e->getMessage()]);
        }
        Auditoria::registrar('plataforma.mercadopago_teste', $request->user(), null,
            ['valor' => $valor, 'referencia' => $referencia, 'preferencia' => $pref['id']]);

        return redirect()->away($pref['init_point']);
    }

    /** Volta do Checkout Pro: mostra os IDs do pagamento e do pedido (order) e confere o pagamento na API. */
    public function retornoTeste(Request $request)
    {
        $q = $request->only(['payment_id', 'collection_id', 'status', 'collection_status', 'merchant_order_id', 'preference_id', 'external_reference', 'payment_type']);
        $id = $q['payment_id'] ?? $q['collection_id'] ?? null;
        $pagamento = [];
        if ($id && $id !== 'null') {
            try {
                $pagamento = MercadoPago::pagamento(MercadoPago::tokenAplicacao(), (string) $id);
            } catch (\Throwable $e) {
                $pagamento = [];
            }
        }

        return view('admin.teste-retorno', ['q' => $q, 'pagamento' => $pagamento]);
    }

    public function mercadoPago()
    {
        return view('admin.mercadopago', ['credenciais' => MercadoPago::situacaoCredenciais(), 'configurado' => MercadoPago::configurado(), 'pkce' => MercadoPago::usaPkce(),
            'avisos' => DB::table('webhook_evento')->orderByDesc('id')->limit(10)->get()]);
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

        $pkce = $request->boolean('pkce');
        if ($pkce !== MercadoPago::usaPkce()) {
            MercadoPago::definirPkce($pkce);
            Auditoria::registrar('plataforma.mercadopago_pkce', $request->user(), null, ['pkce' => $pkce]);
        }
        $alteradas = array_keys(array_filter($dados, 'filled'));
        if (! $alteradas && $request->has('so_pkce')) {
            return redirect()->route('admin.mercadopago')->with('ok', 'PKCE '.($pkce ? 'ativado' : 'desativado').'.');
        }
        if (! $alteradas) {
            return back()->withErrors(['client_id' => 'Preencha pelo menos um campo. Campos em branco mantêm o valor atual.']);
        }
        MercadoPago::salvarCredenciais($dados);
        Auditoria::registrar('plataforma.mercadopago_credenciais', $request->user(), null, ['alteradas' => $alteradas]);

        return redirect()->route('admin.mercadopago')->with('ok', 'Chaves do Mercado Pago salvas. Os segredos ficam criptografados no banco.');
    }
}
