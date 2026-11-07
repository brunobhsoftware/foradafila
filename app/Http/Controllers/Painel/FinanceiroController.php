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
    public function index()
    {
        Gate::authorize('financeiro.ver');
        $conta = $this->contaMercadoPago();

        return view('painel.financeiro.index', [
            'conta' => $conta,
            'configurado' => MercadoPago::configurado(),
            'ambiente' => config('pagamentos.mercadopago.ambiente'),
        ]);
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
            return redirect()->route('financeiro')->withErrors(['mercadopago' => 'Autorização inválida ou expirada. Clique em "Conectar Mercado Pago" de novo.']);
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('financeiro')->withErrors(['mercadopago' => 'A conexão foi cancelada no Mercado Pago.']);
        }

        try {
            $t = MercadoPago::trocarCodigo((string) $request->query('code'), $sessao['verifier']);
        } catch (\Throwable $e) {
            Log::warning('Mercado Pago OAuth: '.$e->getMessage());

            return redirect()->route('financeiro')->withErrors(['mercadopago' => $e->getMessage()]);
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

        return redirect()->route('financeiro')->with('ok', 'Conta do Mercado Pago conectada'.($conta->email_conta ? " ({$conta->email_conta})" : '').'.');
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

        return redirect()->route('financeiro')->with('ok', 'Conta do Mercado Pago desconectada. Os eventos pagos não poderão vender até conectar de novo.');
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
