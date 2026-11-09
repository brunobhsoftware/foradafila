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

        return redirect()->route('comprador.ingressos')->with('ok', ! empty($dados['senha']) ? 'Pronto! Senha criada; da próxima vez você também pode entrar com e-mail e senha.' : 'Pronto! Você entrou na sua conta.');
    }

    /** Meus dados (comprador logado). */
    public function dados(Request $request)
    {
        return view('comprador.dados', ['comprador' => $request->user('comprador')]);
    }

    public function salvarDados(Request $request)
    {
        $c = $request->user('comprador');
        $request->merge(['cpf' => Documento::digitos((string) $request->input('cpf'))]);
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'cpf' => [$c->cpf ? 'prohibited' : 'nullable', new DocumentoValido('PF')],
            'senha' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['cpf.prohibited' => 'O CPF já está cadastrado.'], ['senha' => 'senha', 'cpf' => 'CPF']);
        $c->forceFill(array_filter([
            'nome' => trim($d['nome']), 'telefone' => preg_replace('/\D/', '', (string) ($d['telefone'] ?? '')) ?: null,
            'cpf' => ! $c->cpf && ! empty($d['cpf']) ? $d['cpf'] : null,
            'senha_hash' => ! empty($d['senha']) ? Hash::make($d['senha']) : null,
        ], fn ($v, $k) => $v !== null || $k === 'telefone', ARRAY_FILTER_USE_BOTH))->save();
        if ($c->cpf) {
            CadastroPessoa::registrar($c->cpf, $c->nome, $c->email, $c->telefone, 'cadastro');
        }

        return redirect()->route('comprador.dados')->with('ok', 'Dados salvos.');
    }
}
