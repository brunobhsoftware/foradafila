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

    /**
     * Pagamento de teste pela API de Orders (Checkout Pro com Orders), com os campos que a
     * "Qualidade da integração" do Mercado Pago pede: comprador completo, itens, URLs de retorno,
     * descrição na fatura e additional_info.
     */
    public function testarPagamento(Request $request)
    {
        $d = $request->validate([
            'valor' => ['required', 'numeric', 'min:1', 'max:100'],
            'ambiente' => ['required', 'in:teste,producao'],
            'email' => ['required', 'email:filter', 'max:150'],
            'nome' => ['required', 'string', 'max:150'],
            'cpf' => ['required', 'string', 'regex:/^\D*(\d\D*){11}$/'],
            'telefone' => ['nullable', 'string', 'regex:/^\D*(\d\D*){10,11}$/'],
        ], ['cpf.regex' => 'Informe os 11 números do CPF.', 'telefone.regex' => 'Telefone com DDD (10 ou 11 números).'],
            ['valor' => 'valor', 'email' => 'e-mail do comprador', 'nome' => 'nome completo do comprador']);
        // Um campo só (nome completo): o Mercado Pago pede nome e sobrenome separados, então dividimos aqui.
        $partes = preg_split('/\s+/', trim($d['nome']), 2);
        [$primeiro, $sobrenome] = [$partes[0], $partes[1] ?? $partes[0]];
        \App\Support\CadastroPessoa::registrar($d['cpf'], $d['nome'], $d['email'], $d['telefone'] ?? null, 'pagamento_teste');
        $valor = number_format((float) $d['valor'], 2, '.', '');
        $teste = $d['ambiente'] === 'teste';
        try {
            $token = $teste ? MercadoPago::tokenTeste() : MercadoPago::tokenAplicacao();
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['valor' => $e->getMessage()]);
        }
        if (! $token) {
            return back()->withInput()->withErrors(['valor' => $teste
                ? 'Cadastre primeiro as chaves de teste (Access Token, ou Client ID e Client Secret de teste).'
                : 'Cadastre primeiro as chaves de produção.']);
        }

        $referencia = 'teste-'.\Illuminate\Support\Str::uuid();
        $volta = fn (string $r) => route('admin.mercadopago.teste.retorno', ['ref' => $referencia, 'resultado' => $r]);
        $fone = preg_replace('/\D/', '', (string) ($d['telefone'] ?? ''));
        $usuario = $request->user();
        $agora = now()->toIso8601String();
        $dados = [
            'type' => 'online',
            'processing_mode' => 'manual',
            'total_amount' => $valor,
            'external_reference' => $referencia,
            'description' => 'Teste de integração Fora da Fila',
            'payer' => array_filter([
                'email' => $d['email'],
                'first_name' => $primeiro,
                'last_name' => $sobrenome,
                'identification' => ['type' => 'CPF', 'number' => preg_replace('/\D/', '', $d['cpf'])],
                'phone' => $fone ? ['area_code' => substr($fone, 0, 2), 'number' => substr($fone, 2)] : null,
            ]),
            'items' => [[
                'external_code' => 'teste-ff',
                'title' => 'Ingresso de teste Fora da Fila',
                'description' => 'Ingresso de teste para validar a integração do Fora da Fila',
                'category_id' => 'tickets',
                'quantity' => 1,
                'unit_price' => $valor,
            ]],
            'config' => [
                'statement_descriptor' => 'FORADAFILA',
                'online' => ['success_url' => $volta('sucesso'), 'failure_url' => $volta('falha'), 'pending_url' => $volta('pendente')],
            ],
            'additional_info' => [
                'payer.authentication_type' => 'WEB',
                'payer.registration_date' => \Illuminate\Support\Carbon::parse($usuario->criado_em ?? 'now')->toIso8601String(),
                'payer.is_first_purchase_online' => true,
                'payer.last_purchase' => $agora,
            ],
        ];
        try {
            $order = MercadoPago::criarOrder($token, $dados);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Cache::forget('mp_token_aplicacao_'.md5((string) MercadoPago::credencial('client_id')));

            return back()->withInput()->withErrors(['valor' => $e->getMessage()]);
        }
        \Illuminate\Support\Facades\Cache::put('mp_teste_'.$referencia, ['order' => $order['id'], 'ambiente' => $d['ambiente']], now()->addHours(6));
        Auditoria::registrar('plataforma.mercadopago_teste', $usuario, null,
            ['valor' => $valor, 'ambiente' => $d['ambiente'], 'referencia' => $referencia, 'order' => $order['id']]);

        return view('admin.teste-link', [
            'link' => $order['checkout_url'] ?? '', 'linkSandbox' => null,
            'valor' => (float) $valor, 'teste' => $teste, 'referencia' => $referencia, 'orderId' => $order['id'],
        ]);
    }

    /** Volta do Checkout Pro: mostra a order e o pagamento. A consulta à API é só para administrador logado. */
    public function retornoTeste(Request $request)
    {
        $ref = (string) $request->query('ref', '');
        $info = $ref !== '' ? \Illuminate\Support\Facades\Cache::get('mp_teste_'.$ref) : null;
        $admin = (bool) $request->user()?->admin_plataforma;
        $order = [];
        $contaEmpresa = $info && ! empty($info['conta']) ? \App\Models\ContaPagamento::withoutGlobalScopes()->find($info['conta']) : null;
        if (($admin || $contaEmpresa) && $info) {
            try {
                $token = $contaEmpresa
                    ? decrypt((string) ($info['ambiente'] === 'teste' ? $contaEmpresa->teste_access_token_cript : $contaEmpresa->access_token_cript), false)
                    : ($info['ambiente'] === 'teste' ? MercadoPago::tokenTeste() : MercadoPago::tokenAplicacao());
                $order = $token ? MercadoPago::order($token, $info['order']) : [];
            } catch (\Throwable $e) {
                $order = [];
            }
        }

        return view('admin.teste-retorno', [
            'ref' => $ref, 'resultado' => (string) $request->query('resultado', ''), 'info' => $info,
            'order' => $order, 'extras' => $request->except(['ref', 'resultado']), 'admin' => $admin,
        ]);
    }

    /** De quem é cada token (conta, apelido, se é usuário de teste), para diagnosticar "uma das partes é de teste". */
    public function verificarTokens(Request $request)
    {
        $ambiente = $request->input('ambiente') === 'teste' ? 'teste' : 'producao';
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
            $resultado = $ambiente === 'teste' ? $info(MercadoPago::tokenTeste())
                : (MercadoPago::credencial('access_token') || MercadoPago::configurado() ? $info(MercadoPago::tokenAplicacao()) : null);
        } catch (\Throwable $e) {
            $resultado = ['erro' => $e->getMessage()];
        }

        return redirect()->to(route('admin.mercadopago').'#ambiente-'.$ambiente)->with('verificacao', [$ambiente => $resultado]);
    }

    /** Atualizações do banco aplicadas pela Administração (sem phpMyAdmin). */
    /** Últimos e-mails (mensagem_envio): para conferir se saiu ou por que falhou. Sem o corpo (pode ter dados pessoais). */
    public function emails(Request $request)
    {
        $busca = trim((string) $request->query('busca'));
        $envios = DB::table('mensagem_envio')->where('canal', 'email')
            ->when($busca !== '', fn ($q) => $q->where('destinatario', 'like', '%'.$busca.'%'))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->limit(100)->get(['id', 'tipo', 'destinatario', 'assunto', 'status', 'erro', 'criado_em', 'enviado_em']);

        $contas = $busca !== '' ? [
            'produtores' => DB::table('usuario')->where('email', 'like', '%'.$busca.'%')->limit(10)->get(['uuid', 'nome', 'email', 'ativo', 'senha_hash']),
            'organizacoes' => DB::table('organizador')->where('email_contato', 'like', '%'.$busca.'%')->limit(10)->get(['nome_fantasia', 'razao_social', 'email_contato']),
        ] : null;

        return view('admin.emails', compact('envios', 'busca', 'contas'));
    }

    public function banco()
    {
        return view('admin.banco', ['scripts' => \App\Support\AtualizacaoBanco::lista()]);
    }

    public function aplicarBanco(Request $request)
    {
        $arquivo = (string) $request->validate(['arquivo' => ['required', 'string', 'max:120']])['arquivo'];
        try {
            $n = \App\Support\AtualizacaoBanco::aplicar($arquivo);
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->withErrors(['banco' => 'O banco recusou um comando de '.$arquivo.': '.mb_substr($e->getMessage(), 0, 300)]);
        }
        Auditoria::registrar('plataforma.banco_atualizar', $request->user(), null, ['arquivo' => $arquivo, 'comandos' => $n]);

        return redirect()->route('admin.banco')->with('ok', "{$arquivo} aplicado ({$n} comando(s)). Recarregue as telas do painel para ver as novidades.");
    }

    public function vendasTeste(Request $request)
    {
        $teste = $request->boolean('vendas_teste');
        DB::table('parametro_sistema')->updateOrInsert(['chave' => 'mp_vendas_teste'], ['valor' => $teste ? '1' : '0', 'descricao' => 'Checkout das páginas de evento com as credenciais de teste']);
        Auditoria::registrar('plataforma.mercadopago_vendas_teste', $request->user(), null, ['teste' => $teste]);

        return back()->with('ok', $teste ? 'Vendas em modo de teste: o checkout usa as credenciais de teste.' : 'Vendas reais: o checkout usa as credenciais de produção.');
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
            'access_token' => ['nullable', 'string', 'max:300', 'regex:/^APP_USR-\S+$/'],
            'teste_webhook_secret' => ['nullable', 'string', 'max:200', 'regex:/^\S+$/'],
            'teste_client_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'teste_client_secret' => ['nullable', 'string', 'max:200', 'regex:/^\S+$/'],
        ], [
            'access_token.regex' => 'O Access Token de produção começa com APP_USR- e não tem espaços.',
            'teste_access_token.regex' => 'O Access Token de teste começa com APP_USR- (ou TEST-) e não tem espaços.',
            'client_id.regex' => 'O Client ID tem só letras, números, "-" e "_".',
            'client_secret.regex' => 'O Client Secret não pode ter espaços.',
            'webhook_secret.regex' => 'A assinatura secreta não pode ter espaços.',
        ], ['client_id' => 'Client ID', 'client_secret' => 'Client Secret', 'webhook_secret' => 'assinatura secreta', 'teste_access_token' => 'Access Token de teste', 'teste_webhook_secret' => 'assinatura secreta de teste', 'teste_client_id' => 'Client ID de teste', 'teste_client_secret' => 'Client Secret de teste', 'access_token' => 'Access Token']);

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
