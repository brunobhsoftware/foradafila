<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Comprador;
use App\Models\Pedido;
use App\Rules\DocumentoValido;
use App\Support\CadastroPessoa;
use App\Support\CodigoVerificacao;
use App\Support\Documento;
use App\Support\Notificacoes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Área do comprador sem depender de senha (decisão do Bruno, 09/11/2026): "Acessar meus ingressos" (na tela dos ingressos)
 * ou "Entrar com código" manda um código de 6 dígitos ao e-mail; com ele a pessoa entra e, se ainda não tem senha,
 * pode aproveitar para criar. Também a página "Meus dados".
 */
class AcessoCompradorController extends Controller
{
    private const FINALIDADE = 'acesso-comprador';

    /** Botão "Acessar meus ingressos" da tela dos ingressos do pedido. */
    public function doPedido(Request $request, string $pedido)
    {
        $p = Pedido::withoutGlobalScopes()->where('uuid', $pedido)->firstOrFail();
        $comprador = Comprador::findOrFail($p->comprador_id);
        if (Auth::guard('comprador')->id() === $comprador->id) {
            return redirect()->route('comprador.ingressos');
        }
        abort_unless(in_array($p->uuid, (array) session('pedidos_compra', []), true), 403);

        return $this->enviarPara($comprador);
    }

    /** "Entrar com código" (tela de entrar): resposta igual exista ou não a conta. */
    public function porEmail(Request $request)
    {
        $email = mb_strtolower(trim((string) $request->validate(['email' => ['required', 'email:filter']])['email']));
        $comprador = Comprador::where('email', $email)->whereNull('anonimizado_em')->first();
        if ($comprador) {
            return $this->enviarPara($comprador);
        }
        session(['acesso_comprador' => null, 'acesso_email' => $email]);

        return redirect()->route('comprador.codigo')->with('ok', 'Se houver ingressos com este e-mail, enviamos um código. Confira também o spam.');
    }

    private function enviarPara(Comprador $comprador)
    {
        session(['acesso_comprador' => $comprador->id, 'acesso_email' => $comprador->email]);
        if ($codigo = CodigoVerificacao::gerar($comprador->id, self::FINALIDADE)) {
            Notificacoes::codigoVerificacao($comprador->nome, $comprador->email, $codigo, 'acessar os seus ingressos');
        }

        return redirect()->route('comprador.codigo')
            ->with('ok', 'Enviamos um código de 6 dígitos para '.InscricaoController::mascararEmail($comprador->email).'. Ele vale por '.CodigoVerificacao::VALIDADE_MIN.' minutos.');
    }

    public function codigo()
    {
        $email = session('acesso_email');
        abort_unless($email, 404);
        $comprador = session('acesso_comprador') ? Comprador::find(session('acesso_comprador')) : null;

        return view('comprador.codigo', ['emailMascarado' => InscricaoController::mascararEmail($email),
            'semSenha' => ! $comprador || ! $comprador->senha_hash]);
    }

    public function confirmar(Request $request)
    {
        $dados = $request->validate([
            'codigo' => ['required', 'string'],
            'senha' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['codigo.required' => 'Digite o código enviado por e-mail.'], ['senha' => 'senha']);
        $comprador = session('acesso_comprador') ? Comprador::find(session('acesso_comprador')) : null;
        $erro = $comprador ? CodigoVerificacao::conferir($comprador->id, self::FINALIDADE, $dados['codigo']) : 'Código incorreto. Peça um novo código.';
        if ($erro) {
            return back()->withErrors(['codigo' => $erro]);
        }
        if (! empty($dados['senha'])) {
            $comprador->forceFill(['senha_hash' => Hash::make($dados['senha'])])->save();
        }
        if (! $comprador->email_verificado_em) {
            $comprador->forceFill(['email_verificado_em' => now()])->save(); // provou que o e-mail é dele
        }
        session()->forget(['acesso_comprador', 'acesso_email']);
        Auth::guard('comprador')->login($comprador, true);
        $request->session()->regenerate();
        session(['comprador_verificado' => $comprador->id]); // validou o e-mail com o código
        if ($volta = session()->pull('acesso_volta')) {
            return redirect()->route($volta)->with('ok', 'E-mail confirmado. Agora você pode editar os seus dados.');
        }

        return redirect()->route('comprador.ingressos')->with('ok', ! empty($dados['senha']) ? 'Pronto! Senha criada; da próxima vez você também pode entrar com e-mail e senha.' : 'Pronto! Você entrou na sua conta.');
    }

    /** Meus dados (comprador logado). Editar só depois de entrar com senha ou com o código do e-mail nesta sessão. */
    public static function verificado(): bool
    {
        return Auth::guard('comprador')->check() && session('comprador_verificado') === Auth::guard('comprador')->id();
    }

    public function dados(Request $request)
    {
        return view('comprador.dados', ['comprador' => $request->user('comprador'), 'verificado' => self::verificado(),
            'emailMascarado' => InscricaoController::mascararEmail($request->user('comprador')->email)]);
    }

    /** "Confirmar meu e-mail" em Meus dados: manda o código e volta para Meus dados. */
    public function codigoDados(Request $request)
    {
        session(['acesso_volta' => 'comprador.dados']);

        return $this->enviarPara($request->user('comprador'));
    }

    public function salvarDados(Request $request)
    {
        $c = $request->user('comprador');
        abort_unless(self::verificado(), 403, 'Confirme o seu e-mail com o código antes de editar os dados.');
        $request->merge(['cpf' => Documento::digitos((string) $request->input('cpf'))]);
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'telefone' => ['required', 'string', 'min:10', 'max:20'],
            'data_nascimento' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'cpf' => [$c->cpf ? 'prohibited' : 'required', new DocumentoValido('PF')],
        ], ['telefone.min' => 'Informe o celular com DDD.', 'cpf.prohibited' => 'O CPF já está cadastrado.', 'data_nascimento.before' => 'A data de nascimento tem de ser anterior a hoje.'],
            ['senha' => 'senha', 'cpf' => 'CPF', 'data_nascimento' => 'data de nascimento']);
        $c->forceFill(array_filter([
            'nome' => trim($d['nome']), 'telefone' => preg_replace('/\D/', '', (string) ($d['telefone'] ?? '')) ?: null,
            'data_nascimento' => $d['data_nascimento'] ?? null,
            'cpf' => ! $c->cpf && ! empty($d['cpf']) ? $d['cpf'] : null,
        ], fn ($v, $k) => $v !== null || in_array($k, ['telefone', 'data_nascimento'], true), ARRAY_FILTER_USE_BOTH))->save();
        if ($c->cpf) {
            CadastroPessoa::registrar($c->cpf, $c->nome, $c->email, $c->telefone, 'cadastro');
            try { // V016: data de nascimento também no cadastro único
                \Illuminate\Support\Facades\DB::table('pessoa')->where('cpf', $c->cpf)->update(['data_nascimento' => $c->data_nascimento]);
            } catch (\Throwable $e) {
            }
        }

        return redirect()->route('comprador.dados')->with('ok', 'Seu cadastro foi atualizado.');
    }

    /** "Enviar para meu e-mail": reenvia a confirmação com o PDF dos ingressos ao e-mail do comprador. */
    public function reenviar(Request $request, string $uuid)
    {
        $c = $request->user('comprador');
        $p = Pedido::withoutGlobalScopes()->where('uuid', $uuid)->where('comprador_id', $c->id)->firstOrFail();
        $evento = \App\Models\Evento::withoutGlobalScopes()->with('organizador')->findOrFail($p->evento_id);
        app(\App\Support\Tenant::class)->definir($evento->organizador);
        $evento->load('local');
        $p->load(['ingressos.participante', 'ingressos.tipo', 'ingressos.lote']);
        abort_if($p->ingressos->whereIn('status', ['valido', 'utilizado'])->isEmpty(), 422, 'Este pedido não tem ingressos válidos.');
        Notificacoes::ingressosConfirmados($p, $c, $evento, $evento->organizador, $p->ingressos);

        return back()->with('ok', 'Enviamos os ingressos do pedido '.$p->codigo.' para '.InscricaoController::mascararEmail($c->email).', em PDF.');
    }

    /** Alterar e-mail: com o e-mail atual validado nesta sessão, manda um código ao e-mail NOVO; só troca depois do código. */
    public function email(Request $request)
    {
        return view('comprador.email', ['verificado' => self::verificado(), 'novo' => session('email_novo'),
            'emailMascarado' => InscricaoController::mascararEmail($request->user('comprador')->email)]);
    }

    public function emailCodigo(Request $request)
    {
        abort_unless(self::verificado(), 403);
        $c = $request->user('comprador');
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $novo = $request->validate(['email' => ['required', 'email:filter', 'max:150',
            \Illuminate\Validation\Rule::unique('comprador', 'email')]], ['email.unique' => 'Este e-mail já está em outro cadastro.'], ['email' => 'novo e-mail'])['email'];
        if ($novo === $c->email) {
            return back()->withErrors(['email' => 'Este já é o seu e-mail.']);
        }
        session(['email_novo' => $novo]);
        if ($codigo = CodigoVerificacao::gerar($c->id, 'email-comprador')) {
            Notificacoes::codigoVerificacao($c->nome, $novo, $codigo, 'confirmar o seu novo e-mail');
        }

        return redirect()->route('comprador.email')->with('ok', 'Enviamos um código para '.$novo.'. Digite-o abaixo para confirmar a troca.');
    }

    public function salvarEmail(Request $request)
    {
        abort_unless(self::verificado() && session('email_novo'), 403);
        $c = $request->user('comprador');
        $codigo = (string) $request->validate(['codigo' => ['required', 'string']], ['codigo.required' => 'Digite o código.'])['codigo'];
        if ($erro = CodigoVerificacao::conferir($c->id, 'email-comprador', $codigo)) {
            return back()->withErrors(['codigo' => $erro]);
        }
        $novo = session()->pull('email_novo');
        if (Comprador::where('email', $novo)->where('id', '!=', $c->id)->exists()) {
            return redirect()->route('comprador.email')->withErrors(['email' => 'Este e-mail já está em outro cadastro.']);
        }
        $antigo = $c->email;
        $c->forceFill(['email' => $novo, 'email_verificado_em' => now()])->save();
        Notificacoes::contaAlterada($c->nome, $antigo, 'O e-mail da sua conta no Fora da Fila foi trocado para '.InscricaoController::mascararEmail($novo).'. Se não foi você, responda ao organizador do evento.');
        Notificacoes::contaAlterada($c->nome, $novo, 'Este passou a ser o e-mail da sua conta no Fora da Fila. Os próximos ingressos chegam aqui.');
        if ($c->cpf) {
            CadastroPessoa::registrar($c->cpf, $c->nome, $novo, $c->telefone, 'cadastro');
        }

        return redirect()->route('comprador.dados')->with('ok', 'Seu e-mail foi alterado para '.$novo.'.');
    }

    /** Alterar senha (botão próprio; só quem já tem senha e com o e-mail validado nesta sessão). */
    public function senha(Request $request)
    {
        $c = $request->user('comprador');

        return view('comprador.senha', ['criar' => ! $c->senha_hash] + ['verificado' => self::verificado(), 'emailMascarado' => InscricaoController::mascararEmail($c->email)]);
    }

    public function salvarSenha(Request $request)
    {
        abort_unless(self::verificado(), 403, 'Confirme o seu e-mail com o código antes de alterar a senha.');
        $c = $request->user('comprador');
        $d = $request->validate([
            'senha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['senha' => 'nova senha']);
        $c->forceFill(['senha_hash' => Hash::make($d['senha'])])->save();
        Notificacoes::contaAlterada($c->nome, $c->email, 'A senha da sua conta no Fora da Fila foi alterada.');

        return redirect()->route('comprador.dados')->with('ok', 'Sua senha foi salva.');
    }
}
