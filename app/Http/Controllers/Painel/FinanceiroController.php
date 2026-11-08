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

        $org = \App\Models\Organizador::findOrFail(app(Tenant::class)->id());

        return view('painel.financeiro.index', [
            'org' => $org,
            'v012' => \App\Support\Esquema::temV012(),
            'recebimento' => \App\Support\Recebimento::conta($org->id),
            'propria' => \App\Support\Esquema::temV012() ? \App\Support\Recebimento::conta($org->id, 'propria') : null,
            'verificacao' => session('verificacao_mp'),
            'conexaoDireta' => (bool) config('pagamentos.mercadopago.conexao_direta'),
            'conta' => $conta,
            'configurado' => MercadoPago::configurado(),
            'ambiente' => config('pagamentos.mercadopago.ambiente'),
        ]);
    }

    /** Dados para o repasse (Pix e/ou conta bancária). Pelo menos um dos dois é obrigatório. */
    public function salvarRecebimento(Request $request)
    {
        Gate::authorize('financeiro.contas');
        abort_unless(\App\Support\Esquema::temV012(), 404);
        $org = \App\Models\Organizador::findOrFail(app(Tenant::class)->id());
        foreach (['agencia', 'conta'] as $k) {
            $request->merge([$k => $request->filled($k) ? strtoupper(trim((string) $request->input($k))) : null]);
        }
        $tipo = $request->input('pix_tipo');
        $chave = trim((string) $request->input('pix_chave'));
        if (in_array($tipo, ['cpf', 'cnpj', 'telefone'], true)) {
            $chave = preg_replace('/\D/', '', $chave);
        } elseif ($tipo === 'email') {
            $chave = mb_strtolower($chave);
        }
        $request->merge(['pix_chave' => $chave ?: null]);
        $regrasChave = match ($tipo) {
            'cpf' => [new \App\Rules\DocumentoValido('PF')],
            'cnpj' => [new \App\Rules\DocumentoValido('PJ')],
            'email' => ['email:filter', 'max:140'],
            'telefone' => ['regex:/^[0-9]{10,11}$/'],
            'aleatoria' => ['regex:/^[0-9a-fA-F]{8}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{12}$/'],
            default => [],
        };
        $dados = $request->validate([
            'recebimento_preferido' => ['required', 'in:pix,conta'],
            'pix_tipo' => ['nullable', 'required_with:pix_chave', 'required_if:recebimento_preferido,pix', 'in:'.implode(',', array_keys(\App\Support\Recebimento::PIX_TIPOS))],
            'pix_chave' => array_merge(['nullable', 'required_with:pix_tipo', 'required_if:recebimento_preferido,pix', 'string'], $regrasChave),
            'banco' => ['nullable', 'required_with:agencia,conta,favorecido', 'required_if:recebimento_preferido,conta', 'string', 'max:80'],
            'agencia' => ['nullable', 'required_with:banco,conta', 'required_if:recebimento_preferido,conta', 'string', 'max:10', 'regex:/^[0-9]{1,6}(-?[0-9X])?$/'],
            'conta' => ['nullable', 'required_with:banco,agencia', 'required_if:recebimento_preferido,conta', 'string', 'max:20', 'regex:/^[0-9]{1,15}-?[0-9X]?$/'],
            'favorecido' => ['nullable', 'required_with:banco,agencia,conta', 'required_if:recebimento_preferido,conta', 'string', 'max:150'],
        ], [
            'pix_chave.regex' => $tipo === 'telefone' ? 'Celular com DDD (10 ou 11 números).' : 'Chave aleatória no formato 123e4567-e89b-12d3-a456-426614174000.',
            'required_if' => 'Informe :attribute (é a forma de recebimento escolhida).',
            'required_with' => 'Para a conta bancária, informe banco, agência, conta e favorecido.',
            'agencia.regex' => 'Agência: só números, com o dígito se houver (ex.: 1234 ou 1234-5).',
            'conta.regex' => 'Conta: números com o dígito (ex.: 12345-6).',
        ], ['pix_tipo' => 'o tipo da chave Pix', 'pix_chave' => 'a chave Pix', 'banco' => 'o banco', 'agencia' => 'a agência', 'conta' => 'a conta', 'favorecido' => 'o favorecido']);

        $antes = $org->getAttributes();
        $org->forceFill($dados + ['recebimento_atualizado_em' => now()])->save();
        \App\Support\Recebimento::sincronizar($org);
        Auditoria::registrar('financeiro.recebimento', $org, $antes, $org->fresh()->getAttributes());

        return redirect()->route('financeiro')->with('ok', 'Dados de recebimento salvos. Os repasses vão para: '.\App\Support\Recebimento::destino($org->fresh()).'.');
    }

    /** Aplicação Mercado Pago da própria empresa: grava as chaves de um ambiente (em branco mantém as já salvas). */
    public function salvarPropria(Request $request, string $ambiente)
    {
        Gate::authorize('financeiro.contas');
        abort_unless(\App\Support\Esquema::temV012() && in_array($ambiente, ['teste', 'producao'], true), 404);
        $d = $request->validate([
            'client_id' => ['nullable', 'string', 'max:100', 'regex:/^[0-9]+$/'],
            'client_secret' => ['nullable', 'string', 'max:200'],
            'access_token' => ['nullable', 'string', 'max:300', 'regex:/^(APP_USR|TEST)-/'],
            'public_key' => ['nullable', 'string', 'max:255', 'regex:/^(APP_USR|TEST)-/'],
            'webhook_secret' => ['nullable', 'string', 'max:200'],
        ], ['client_id.regex' => 'O Client ID tem só números.', 'access_token.regex' => 'O Access Token começa com APP_USR- (ou TEST-).',
            'public_key.regex' => 'A Public Key começa com APP_USR- (ou TEST-).']);
        $org = \App\Models\Organizador::findOrFail(app(Tenant::class)->id());
        $conta = \App\Support\Recebimento::contaPropria($org);
        $p = $ambiente === 'teste' ? 'teste_' : '';
        $cripto = fn ($v) => encrypt(trim($v), false);
        $novos = array_filter([
            $p.'client_id' => $d['client_id'] ?? null,
            $p.'client_secret_cript' => filled($d['client_secret'] ?? null) ? $cripto($d['client_secret']) : null,
            ($ambiente === 'teste' ? 'teste_access_token_cript' : 'access_token_cript') => filled($d['access_token'] ?? null) ? $cripto($d['access_token']) : null,
            ($ambiente === 'teste' ? 'teste_public_key' : 'public_key') => $d['public_key'] ?? null,
            $p.'webhook_secret_cript' => filled($d['webhook_secret'] ?? null) ? $cripto($d['webhook_secret']) : null,
        ]);
        if ($ambiente === 'producao' && isset($novos['access_token_cript'])) {
            $novos += ['status' => 'em_homologacao', 'conectado_por' => auth()->id(), 'conectado_em' => now()]; // vira "ativa" ao verificar
        }
        $conta->forceFill($novos)->save();
        Auditoria::registrar('financeiro.mp_propria_chaves', $conta, null, ['ambiente' => $ambiente, 'campos' => array_keys($novos)]);

        return redirect()->route('financeiro', ['mp' => 1])->with('ok', 'Chaves '.($ambiente === 'teste' ? 'de teste' : 'de produção').' salvas'
            .($ambiente === 'producao' && isset($novos['access_token_cript']) ? '. Clique em "Verificar de quem é o token" para ativar.' : '.'));
    }

    /** Confere de quem são os tokens (/users/me). O de produção válido ativa a conta própria. */
    public function verificarPropria()
    {
        Gate::authorize('financeiro.contas');
        $org = \App\Models\Organizador::findOrFail(app(Tenant::class)->id());
        $conta = \App\Support\Recebimento::conta($org->id, 'propria');
        abort_unless($conta, 404);
        $info = function (?string $cript) {
            if (! $cript) {
                return null;
            }
            try {
                $u = MercadoPago::usuario(decrypt($cript, false));
            } catch (\Throwable $e) {
                $u = [];
            }
            $tags = (array) ($u['tags'] ?? []);

            return $u ? ['ok' => true, 'id' => $u['id'] ?? null, 'email' => $u['email'] ?? null, 'apelido' => $u['nickname'] ?? null,
                'teste' => in_array('test_user', $tags, true) || str_contains((string) ($u['email'] ?? ''), '@testuser.')] : ['ok' => false];
        };
        $r = ['producao' => $info($conta->access_token_cript), 'teste' => $info($conta->teste_access_token_cript)];
        if ($r['producao']['ok'] ?? false) {
            $conta->forceFill(['status' => 'ativa', 'homologada_em' => $conta->homologada_em ?? now(), 'conta_externa_id' => $r['producao']['id'],
                'email_conta' => $r['producao']['email'], 'apelido_externo' => $r['producao']['apelido'], 'ultimo_erro' => null])->save();
        } elseif ($conta->access_token_cript) {
            $conta->forceFill(['status' => 'suspensa', 'ultimo_erro' => 'O Mercado Pago recusou o Access Token de produção.', 'ultimo_erro_em' => now()])->save();
        }
        Auditoria::registrar('financeiro.mp_propria_verificar', $conta, null, ['producao' => $r['producao']['ok'] ?? null, 'teste' => $r['teste']['ok'] ?? null]);

        return redirect()->route('financeiro', ['mp' => 1])->with('verificacao_mp', $r);
    }

    /** Escolhe como as vendas são recebidas: pela plataforma (repasse) ou na aplicação própria da empresa. */
    public function modoRecebimento(Request $request)
    {
        Gate::authorize('financeiro.contas');
        $modo = $request->validate(['recebimento_modo' => ['required', 'in:plataforma,propria']])['recebimento_modo'];
        $org = \App\Models\Organizador::findOrFail(app(Tenant::class)->id());
        $conta = \App\Support\Recebimento::conta($org->id, $modo);
        if (! $conta || $conta->status !== 'ativa') {
            return back()->withErrors(['recebimento_modo' => $modo === 'propria'
                ? 'Cadastre e verifique o Access Token de produção da aplicação da empresa antes de usá-la.'
                : 'Cadastre os dados para o repasse antes de receber pela plataforma.']);
        }
        $antes = $org->getAttributes();
        $org->forceFill(['recebimento_modo' => $modo])->save();
        // Eventos ainda não encerrados passam a usar a conta escolhida.
        \App\Models\Evento::whereNotIn('status', ['encerrado', 'cancelado'])->update(['conta_pagamento_id' => $conta->id]);
        Auditoria::registrar('financeiro.recebimento_modo', $org, $antes, ['recebimento_modo' => $modo]);

        return back()->with('ok', $modo === 'propria' ? 'As vendas passam a cair direto na conta do Mercado Pago da empresa.' : 'As vendas passam a ser recebidas pela plataforma, com repasse.');
    }

    /** Vai para a tela de autorização do Mercado Pago. */
    public function conectar(Request $request)
    {
        Gate::authorize('financeiro.contas');
        if (! config('pagamentos.mercadopago.conexao_direta')) {
            return back()->withErrors(['mercadopago' => 'A conexão direta com a sua conta do Mercado Pago estará disponível em breve.']);
        }
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
        return ContaPagamento::where('gateway_id', $this->gatewayId())->when(\App\Support\Esquema::temV012(), fn ($q) => $q->where('modo', 'direta'))->orderByDesc('padrao')->orderByDesc('id')->first();
    }
}
