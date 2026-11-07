<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Support\Auditoria;
use App\Support\CodigoVerificacao;
use App\Support\Notificacoes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Conta de acesso da pessoa logada (produtor/equipe):
 *  - trocar senha: código enviado ao e-mail cadastrado + nova senha com confirmação;
 *  - trocar e-mail: só depois de validar o código enviado ao e-mail ATUAL.
 * Os códigos são de uso único e valem 15 minutos (App\Support\CodigoVerificacao).
 */
class MinhaContaController extends Controller
{
    public function senha(Request $request)
    {
        return view('painel.minha-conta.senha', [
            'codigoEnviado' => CodigoVerificacao::pendente($request->user()->id, 'senha'),
        ]);
    }

    public function enviarCodigoSenha(Request $request)
    {
        return $this->enviarCodigo($request->user(), 'senha', 'trocar a senha');
    }

    public function salvarSenha(Request $request)
    {
        $usuario = $request->user();
        $dados = $request->validate([
            'codigo' => ['required', 'digits:6'],
            'senha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['codigo.digits' => 'O código tem 6 números.'], ['codigo' => 'código', 'senha' => 'nova senha']);

        if ($erro = CodigoVerificacao::conferir($usuario->id, 'senha', $dados['codigo'])) {
            throw ValidationException::withMessages(['codigo' => $erro]);
        }

        $usuario->forceFill(['senha_hash' => Hash::make($dados['senha']), 'tentativas_login' => 0, 'bloqueado_ate' => null])->save();
        $request->session()->regenerate();
        Auditoria::registrar('conta.trocar_senha', $usuario, [], ['senha' => 'alterada']);
        Notificacoes::contaAlterada($usuario->nome, $usuario->email, 'A senha da sua conta foi alterada.');

        return redirect()->route('organizacao.edit')->with('ok', 'Senha alterada.');
    }

    public function email(Request $request)
    {
        return view('painel.minha-conta.email', [
            'codigoEnviado' => CodigoVerificacao::pendente($request->user()->id, 'email'),
        ]);
    }

    public function enviarCodigoEmail(Request $request)
    {
        return $this->enviarCodigo($request->user(), 'email', 'trocar o e-mail de acesso');
    }

    public function salvarEmail(Request $request)
    {
        $usuario = $request->user();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $dados = $request->validate([
            'codigo' => ['required', 'digits:6'],
            'email' => ['required', 'email', 'max:150', Rule::unique('usuario', 'email')->ignore($usuario->id), Rule::notIn([$usuario->email])],
        ], [
            'codigo.digits' => 'O código tem 6 números.',
            'email.unique' => 'Este e-mail já é usado por outra conta.',
            'email.not_in' => 'O novo e-mail é igual ao atual.',
        ], ['codigo' => 'código', 'email' => 'novo e-mail']);

        if ($erro = CodigoVerificacao::conferir($usuario->id, 'email', $dados['codigo'])) {
            throw ValidationException::withMessages(['codigo' => $erro]);
        }

        $anterior = $usuario->email;
        $antes = $usuario->getAttributes();
        $usuario->forceFill(['email' => $dados['email'], 'email_verificado_em' => null])->save();
        Auditoria::registrar('conta.trocar_email', $usuario, $antes);
        // Aviso ao e-mail antigo (segurança) e confirmação no novo.
        Notificacoes::contaAlterada($usuario->nome, $anterior, 'O e-mail de acesso da sua conta foi trocado para '.$dados['email'].'.');
        Notificacoes::contaAlterada($usuario->nome, $dados['email'], 'Este passou a ser o e-mail de acesso da sua conta no Fora da Fila.');

        return redirect()->route('organizacao.edit')->with('ok', 'E-mail de acesso alterado para '.$dados['email'].'. Use-o no próximo login.');
    }

    private function enviarCodigo(Usuario $usuario, string $finalidade, string $acao)
    {
        $codigo = CodigoVerificacao::gerar($usuario->id, $finalidade);
        if ($codigo === null) {
            return back()->with('erro', 'Um código acabou de ser enviado. Aguarde um minuto antes de pedir outro.');
        }
        Notificacoes::codigoVerificacao($usuario->nome, $usuario->email, $codigo, $acao);

        return back()->with('ok', 'Enviamos um código de 6 números para '.$usuario->email.'. Ele vale por '.CodigoVerificacao::VALIDADE_MIN.' minutos.');
    }
}
