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
        $d = $request->validate(['valor' => ['required', 'numeric', 'min:1', 'max:100'], 'ambiente' => ['required', 'in:teste,producao']], [], ['valor' => 'valor']);
        $valor = (float) $d['valor'];
        $teste = $d['ambiente'] === 'teste';
        if ($teste) {
            try {
                $tokenTeste = MercadoPago::tokenTeste();
            } catch (\Throwable $e) {
                return back()->withErrors(['valor' => $e->getMessage()]);
            }
            if (! $tokenTeste) {
                return back()->withErrors(['valor' => 'Cadastre primeiro as credenciais de teste: Access Token de teste, ou Client ID e Client Secret de teste (Credenciais > Teste).']);
            }
        }
        if (! $teste && ! MercadoPago::configurado()) {
            return back()->withErrors(['valor' => 'Cadastre primeiro o Client ID e o Client Secret.']);
        }
        session(['mp_teste_ambiente' => $d['ambiente']]);
        $referencia = 'teste-'.\Illuminate\Support\Str::uuid();
        try {
            $pref = MercadoPago::criarPreferencia($teste ? $tokenTeste : MercadoPago::tokenAplicacao(), [
                'items' => [['id' => 'teste-ff', 'title' => 'Teste de integração Fora da Fila', 'quantity' => 1, 'unit_price' => round($valor, 2), 'currency_id' => 'BRL']],
                'external_reference' => $referencia,
                'back_urls' => array_fill_keys(['success', 'failure', 'pending'], route('admin.mercadopago.teste.retorno')),
                'auto_return' => 'approved',
                'statement_descriptor' => 'FORADAFILA',
            ] + ($teste ? [] : ['notification_url' => url(config('pagamentos.mercadopago.webhook'))]));
            // Teste: sem notification_url, para o Mercado Pago usar (e registrar) o webhook do modo teste do painel;
            // a notification_url da preferência teria prioridade sobre ele.
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Cache::forget('mp_token_aplicacao_'.md5((string) MercadoPago::credencial('client_id')));

            return back()->withErrors(['valor' => $e->getMessage()]);
        }
        Auditoria::registrar('plataforma.mercadopago_teste', $request->user(), null,
            ['valor' => $valor, 'ambiente' => $d['ambiente'], 'referencia' => $referencia, 'preferencia' => $pref['id']]);

        // Mostra o link (copiar / QR Code) para abrir numa janela anônima, outro navegador ou no celular,
        // sem a conta da BH Software logada (o Mercado Pago recusa misturar conta real com teste).
        return view('admin.teste-link', [
            'link' => $pref['init_point'],
            'linkSandbox' => $teste && ! empty($pref['sandbox_init_point']) && $pref['sandbox_init_point'] !== $pref['init_point'] ? $pref['sandbox_init_point'] : null,
            'valor' => $valor, 'teste' => $teste, 'referencia' => $referencia,
        ]);
    }

    /** Volta do Checkout Pro: mostra os IDs do pagamento e do pedido (order) e confere o pagamento na API. */
    public function retornoTeste(Request $request)
    {
        $q = $request->only(['payment_id', 'collection_id', 'status', 'collection_status', 'merchant_order_id', 'preference_id', 'external_reference', 'payment_type']);
        $id = $q['payment_id'] ?? $q['collection_id'] ?? null;
        $pagamento = [];
        $admin = (bool) $request->user()?->admin_plataforma;
        if ($admin && $id && $id !== 'null') {
            try {
                $token = session('mp_teste_ambiente') === 'teste' ? MercadoPago::tokenTeste() : MercadoPago::tokenAplicacao();
                $pagamento = MercadoPago::pagamento($token, (string) $id);
            } catch (\Throwable $e) {
                $pagamento = [];
            }
        }

        return view('admin.teste-retorno', ['q' => $q, 'pagamento' => $pagamento, 'ambiente' => session('mp_teste_ambiente'), 'admin' => $admin]);
    }

    /** De quem é cada token (conta, apelido, se é usuário de teste), para diagnosticar "uma das partes é de teste". */
    public function verificarTokens()
    {
        $info = function (?string $token) {
            if (! $token) {
                return null;
            }
            $u = MercadoPago::usuario($token);
            if (! $u) {
                return ['erro' => 'O Mercado Pago não aceitou este token.'];
            }
            $tags = (array) ($u['tags'] ?? []);

            return [
                'id' => $u['id'] ?? null, 'apelido' => $u['nickname'] ?? null, 'email' => $u['email'] ?? null,
                'teste' => in_array('test_user', $tags, true) || str_contains((string) ($u['email'] ?? ''), '@testuser.com')
                    || str_starts_with(strtoupper((string) ($u['nickname'] ?? '')), 'TEST'),
            ];
        };
        try {
            $producao = MercadoPago::configurado() ? $info(MercadoPago::tokenAplicacao()) : null;
        } catch (\Throwable $e) {
            $producao = ['erro' => $e->getMessage()];
        }

        try {
            $teste = $info(MercadoPago::tokenTeste());
        } catch (\Throwable $e) {
            $teste = ['erro' => $e->getMessage()];
        }

        return back()->with('verificacao', ['producao' => $producao, 'teste' => $teste]);
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
            'teste_access_token' => ['nullable', 'string', 'max:300', 'regex:/^(APP_USR|TEST)-\S+$/'],
            'teste_webhook_secret' => ['nullable', 'string', 'max:200', 'regex:/^\S+$/'],
            'teste_client_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'teste_client_secret' => ['nullable', 'string', 'max:200', 'regex:/^\S+$/'],
        ], [
            'teste_access_token.regex' => 'O Access Token de teste começa com APP_USR- (ou TEST-) e não tem espaços.',
            'client_id.regex' => 'O Client ID tem só letras, números, "-" e "_".',
            'client_secret.regex' => 'O Client Secret não pode ter espaços.',
            'webhook_secret.regex' => 'A assinatura secreta não pode ter espaços.',
        ], ['client_id' => 'Client ID', 'client_secret' => 'Client Secret', 'webhook_secret' => 'assinatura secreta', 'teste_access_token' => 'Access Token de teste', 'teste_webhook_secret' => 'assinatura secreta de teste', 'teste_client_id' => 'Client ID de teste', 'teste_client_secret' => 'Client Secret de teste']);

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
