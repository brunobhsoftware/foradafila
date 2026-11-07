<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\ContaPagamento;
use App\Pagamentos\MercadoPago;
use App\Support\Auditoria;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Financeiro: contas de recebimento (Mercado Pago primeiro). */
class FinanceiroController extends Controller
{
    /** Painel financeiro dos eventos. */
    public function index()
    {
        Gate::authorize('financeiro.ver');
        $eventos = DB::table('vw_dashboard_evento')->where('organizador_id', app(Tenant::class)->id())
            ->whereIn('evento_id', \App\Models\Evento::visiveis()->select('id'))
            ->orderByDesc('data_inicio')->get();
        $totais = (object) [
            'receita' => $eventos->sum('receita_bruta'), 'pedidos' => $eventos->sum('pedidos_pagos'),
            'ingressos' => $eventos->sum('ingressos_vendidos'), 'comissao' => $eventos->sum('comissao_plataforma'),
        ];
        $conta = $this->contaMercadoPago();

        return view('painel.financeiro.index', compact('eventos', 'totais', 'conta'));
    }

    /** Operadoras disponíveis para receber as vendas. */
    public function recebimentos()
    {
        Gate::authorize('financeiro.ver');

        return view('painel.financeiro.recebimentos', ['conta' => $this->contaMercadoPago()]);
    }

    /** Gerenciador de recebimentos do Mercado Pago. */
    public function mercadoPago()
    {
        Gate::authorize('financeiro.ver');

        return view('painel.financeiro.mercadopago', [
            'conta' => $this->contaMercadoPago(),
            'configurado' => MercadoPago::configurado(),
            'admin' => (bool) auth()->user()->admin_plataforma,
            'credenciais' => auth()->user()->admin_plataforma ? MercadoPago::situacaoCredenciais() : [],
        ]);
    }

    /** Chaves da aplicação do Fora da Fila no Mercado Pago: só administrador da plataforma (equipe BH Software). */
    public function credenciais(Request $request)
    {
        abort_unless(auth()->user()->admin_plataforma, 403);
        $dados = $request->validate([
            'client_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'client_secret' => ['nullable', 'string', 'max:200'],
            'webhook_secret' => ['nullable', 'string', 'max:200'],
        ], ['client_id.regex' => 'O Client ID tem só letras, números, "-" e "_".'], [
            'client_id' => 'Client ID', 'client_secret' => 'Client Secret', 'webhook_secret' => 'assinatura secreta',
        ]);
        MercadoPago::salvarCredenciais($dados);
        \App\Models\Auditoria::query(); // noop para manter o import de auditoria simples
        Auditoria::registrar('plataforma.mercadopago_credenciais', auth()->user(), null,
            ['alteradas' => array_keys(array_filter($dados))]);

        return redirect()->route('financeiro.mp')->with('ok', 'Chaves do Mercado Pago salvas (os segredos ficam criptografados).');
    }

    /** Vai para a tela de autorização do Mercado Pago. */
    public function conectar(Request $request)
    {
        Gate::authorize('financeiro.contas');
        if (! MercadoPago::configurado()) {
            return back()->withErrors(['mercadopago' => 'A integração com o Mercado Pago ainda não foi configurada na plataforma.']);
        }
        $state = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put('mp_oauth', ['state' => $state, 'verifier' => $verifier, 'organizador_id' => app(Tenant::class)->id()]);

        return redirect()->away(MercadoPago::urlAutorizacao($state, $verifier));
    }

    /** Volta do Mercado Pago com o código de autorização. */
    public function retorno(Request $request)
    {
        Gate::authorize('financeiro.contas');
        $sessao = $request->session()->pull('mp_oauth');
        $tenant = app(Tenant::class);
        if (! $sessao || ! hash_equals($sessao['state'], (string) $request->query('state')) || $sessao['organizador_id'] !== $tenant->id()) {
            return redirect()->route('financeiro.mp')->withErrors(['mercadopago' => 'Autorização inválida ou expirada. Clique em "Conectar Mercado Pago" de novo.']);
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('financeiro.mp')->withErrors(['mercadopago' => 'A conexão foi cancelada no Mercado Pago.']);
        }

        try {
            $t = MercadoPago::trocarCodigo((string) $request->query('code'), $sessao['verifier']);
        } catch (\Throwable $e) {
            Log::warning('Mercado Pago OAuth: '.$e->getMessage());

            return redirect()->route('financeiro.mp')->withErrors(['mercadopago' => $e->getMessage()]);
        }
        $u = MercadoPago::usuario($t['access_token']);

        $conta = $this->contaMercadoPago() ?? new ContaPagamento([
            'gateway_id' => $this->gatewayId(), 'apelido' => 'Mercado Pago', 'padrao' => 1,
        ]);
        $antes = $conta->exists ? $conta->getAttributes() : null;
        $conta->fill([
            'ambiente' => ($t['live_mode'] ?? false) ? 'producao' : 'sandbox',
            'status' => 'conectada',
            'conta_externa_id' => (string) ($t['user_id'] ?? $u['id'] ?? ''),
            'email_conta' => $u['email'] ?? null,
            'apelido_externo' => $u['nickname'] ?? null,
            'pais_conta' => $u['site_id'] ?? null,
            'public_key' => $t['public_key'] ?? null,
            'access_token_cript' => encrypt($t['access_token'], false),
            'refresh_token_cript' => isset($t['refresh_token']) ? encrypt($t['refresh_token'], false) : null,
            'token_expira_em' => isset($t['expires_in']) ? now('UTC')->addSeconds((int) $t['expires_in']) : null,
            'escopos' => $t['scope'] ?? null,
            'conectado_por' => auth()->id(),
            'conectado_em' => now('UTC'),
            'desconectado_em' => null,
            'ultimo_erro' => null, 'ultimo_erro_em' => null,
        ]);
        $conta->save();
        Auditoria::registrar('conta_pagamento.conectar', $conta, $antes);

        return redirect()->route('financeiro.mp')->with('ok', 'Conta do Mercado Pago conectada'.($conta->email_conta ? " ({$conta->email_conta})" : '').'.');
    }

    public function desconectar()
    {
        Gate::authorize('financeiro.contas');
        $conta = $this->contaMercadoPago();
        abort_unless($conta, 404);
        $antes = $conta->getAttributes();
        $conta->update([
            'status' => 'revogada', 'access_token_cript' => null, 'refresh_token_cript' => null,
            'token_expira_em' => null, 'desconectado_em' => now('UTC'),
        ]);
        Auditoria::registrar('conta_pagamento.desconectar', $conta, $antes);

        return redirect()->route('financeiro.mp')->with('ok', 'Conta do Mercado Pago desconectada. Os eventos pagos não poderão vender até conectar de novo.');
    }

    private function gatewayId(): int
    {
        return (int) DB::table('gateway')->where('codigo', 'mercadopago')->value('id');
    }

    private function contaMercadoPago(): ?ContaPagamento
    {
        return ContaPagamento::where('gateway_id', $this->gatewayId())->orderByDesc('padrao')->orderByDesc('id')->first();
    }
}
