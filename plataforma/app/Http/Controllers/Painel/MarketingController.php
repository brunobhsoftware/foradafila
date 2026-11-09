<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Organizador;
use App\Support\Auditoria;
use App\Support\Email;
use App\Support\Esquema;
use App\Support\SmtpEmpresa;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Organização → Opções de marketing: contas SMTP da empresa (até 5) para os convites por e-mail. */
class MarketingController extends Controller
{
    public function index(Tenant $tenant)
    {
        Gate::authorize('organizador.editar');
        abort_unless(Esquema::temV017(), 404);

        return view('painel.marketing.index', [
            'org' => Organizador::findOrFail($tenant->id()),
            'contas' => DB::table('smtp_conta')->where('organizador_id', $tenant->id())->orderBy('id')->get(),
        ]);
    }

    public function salvar(Request $request, Tenant $tenant, ?string $uuid = null)
    {
        Gate::authorize('organizador.editar');
        abort_unless(Esquema::temV017(), 404);
        $conta = $uuid ? $this->conta($tenant, $uuid) : null;
        if (! $conta && DB::table('smtp_conta')->where('organizador_id', $tenant->id())->count() >= SmtpEmpresa::MAXIMO) {
            return back()->withErrors(['nome' => 'Limite de '.SmtpEmpresa::MAXIMO.' contas de e-mail.']);
        }
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:60'],
            'remetente_nome' => ['required', 'string', 'max:100'],
            'remetente_email' => ['required', 'email:filter', 'max:150'],
            'responder_para' => ['nullable', 'email:filter', 'max:150'],
            'host' => ['required', 'string', 'max:150', 'regex:/^[A-Za-z0-9.-]+$/'],
            'porta' => ['required', 'integer', 'in:25,465,587,2525'],
            'seguranca' => ['required', 'in:ssl,tls,nenhuma'],
            'usuario' => ['required', 'string', 'max:150'],
            'senha' => [$conta ? 'nullable' : 'required', 'string', 'max:255'],
            'limite_hora' => ['required', 'integer', 'min:1', 'max:5000'],
        ], ['host.regex' => 'Servidor: só o nome, ex.: smtp.hostinger.com.', 'porta.in' => 'Use a porta 465 (SSL), 587 (TLS), 25 ou 2525.'],
            ['remetente_email' => 'e-mail do remetente', 'remetente_nome' => 'nome do remetente', 'host' => 'servidor SMTP', 'limite_hora' => 'limite por hora']);

        $dados = collect($d)->except('senha')->all() + ['ativo' => $request->boolean('ativo', true)];
        if (! empty($d['senha'])) {
            $dados['senha_cripto'] = Crypt::encryptString($d['senha']);
            $dados['testado_em'] = null;
        }
        if ($conta) {
            DB::table('smtp_conta')->where('id', $conta->id)->update($dados);
        } else {
            DB::table('smtp_conta')->insert($dados + ['uuid' => (string) Str::uuid(), 'organizador_id' => $tenant->id()]);
        }
        Auditoria::registrar('marketing.smtp_salvar', Organizador::findOrFail($tenant->id()), null, collect($dados)->except('senha_cripto')->all());

        return redirect()->route('marketing.index')->with('ok', "Conta “{$d['nome']}” salva. Use “Enviar teste” para conferir.");
    }

    public function testar(Request $request, Tenant $tenant, string $uuid)
    {
        Gate::authorize('organizador.editar');
        $conta = $this->conta($tenant, $uuid);
        $para = $request->validate(['para' => ['required', 'email:filter']])['para'];
        $html = Email::layout('Teste de envio', '<p>Este é um teste da conta <b>'.e($conta->nome).'</b> ('.e($conta->remetente_email).') no Fora da Fila.</p><p>Se chegou, a conta está pronta para os convites por e-mail.</p>');
        $erro = SmtpEmpresa::enviar($conta, $para, 'Teste de envio — '.$conta->nome, $html, 'Teste de envio da conta '.$conta->nome.' no Fora da Fila.');
        DB::table('smtp_conta')->where('id', $conta->id)->update($erro ? ['ultimo_erro' => $erro] : ['testado_em' => now(), 'ultimo_erro' => null]);

        return back()->with($erro ? 'erro' : 'ok', $erro ? 'O envio falhou: '.$erro : "E-mail de teste enviado para $para. Confira a caixa de entrada (e o spam).");
    }

    public function remover(Tenant $tenant, string $uuid)
    {
        Gate::authorize('organizador.editar');
        $conta = $this->conta($tenant, $uuid);
        DB::table('smtp_conta')->where('id', $conta->id)->delete();
        Auditoria::registrar('marketing.smtp_remover', Organizador::findOrFail($tenant->id()), null, ['nome' => $conta->nome, 'remetente' => $conta->remetente_email]);

        return back()->with('ok', "Conta “{$conta->nome}” removida.");
    }

    private function conta(Tenant $tenant, string $uuid): object
    {
        return DB::table('smtp_conta')->where('organizador_id', $tenant->id())->where('uuid', $uuid)->first() ?? abort(404);
    }
}
