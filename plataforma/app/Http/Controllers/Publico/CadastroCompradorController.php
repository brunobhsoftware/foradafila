<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Comprador;
use App\Rules\DocumentoValido;
use App\Support\CadastroPessoa;
use App\Support\CodigoVerificacao;
use App\Support\Documento;
use App\Support\Notificacoes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * "Editar meus dados" na compra (decisão do Bruno, 09/11/2026): CPF, nome, e-mail, celular e, se quiser, senha.
 * Quem digitou o CPF só edita depois de confirmar o código de 6 dígitos enviado ao e-mail já cadastrado
 * (senão qualquer um mudaria os dados de outra pessoa sabendo o CPF dela).
 */
class CadastroCompradorController extends Controller
{
    private const FINALIDADE = 'cadastro-comprador';

    public function mostrar(Request $request)
    {
        $cpf = Documento::digitos((string) $request->query('cpf', session('cadastro_cpf', '')));
        $comprador = self::compradorDoCpf($cpf);
        abort_unless($comprador, 404);

        return view('publico.cadastro-comprador', [
            'comprador' => $comprador, 'cpf' => $cpf, 'volta' => self::volta($request->query('volta', session('cadastro_volta'))),
            'enviado' => session('cadastro_cpf') === $cpf && CodigoVerificacao::pendente($comprador->id, self::FINALIDADE),
            'emailMascarado' => InscricaoController::mascararEmail($comprador->email),
        ]);
    }

    public function enviarCodigo(Request $request)
    {
        $cpf = Documento::digitos((string) $request->input('cpf'));
        $comprador = self::compradorDoCpf($cpf);
        abort_unless($comprador, 404);
        session(['cadastro_cpf' => $cpf, 'cadastro_volta' => self::volta($request->input('volta'))]);
        if ($codigo = CodigoVerificacao::gerar($comprador->id, self::FINALIDADE)) {
            Notificacoes::codigoVerificacao($comprador->nome, $comprador->email, $codigo, 'editar o seu cadastro');
        }

        return redirect()->route('compra.cadastro', ['cpf' => $cpf])
            ->with('ok', 'Enviamos um código para '.InscricaoController::mascararEmail($comprador->email).'. Ele vale por '.CodigoVerificacao::VALIDADE_MIN.' minutos.');
    }

    public function salvar(Request $request)
    {
        $cpf = (string) session('cadastro_cpf', '');
        $comprador = self::compradorDoCpf($cpf);
        abort_unless($comprador, 404);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $dados = $request->validate([
            'codigo' => ['required', 'string'],
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:filter', 'max:150', Rule::unique('comprador', 'email')->ignore($comprador->id)],
            'telefone' => ['nullable', 'string', 'max:20'],
            'senha' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['email.unique' => 'Este e-mail já está em outro cadastro.', 'codigo.required' => 'Digite o código enviado por e-mail.'],
            ['senha' => 'senha', 'codigo' => 'código']);
        if ($erro = CodigoVerificacao::conferir($comprador->id, self::FINALIDADE, $dados['codigo'])) {
            return back()->withInput($request->except(['senha', 'senha_confirmation', 'codigo']))->withErrors(['codigo' => $erro]);
        }

        $tel = preg_replace('/\D/', '', (string) ($dados['telefone'] ?? '')) ?: null;
        $comprador->forceFill(['nome' => trim($dados['nome']), 'email' => $dados['email'], 'telefone' => $tel, 'cpf' => $comprador->cpf ?: $cpf]
            + (! empty($dados['senha']) ? ['senha_hash' => Hash::make($dados['senha'])] : []))->save();
        CadastroPessoa::registrar($cpf, $comprador->nome, $comprador->email, $tel, 'cadastro');

        // Confirmou o e-mail com o código: pode entrar na conta e seguir a compra já identificado.
        Auth::guard('comprador')->login($comprador);
        $request->session()->regenerate();
        session(['comprador_verificado' => $comprador->id]);
        $volta = self::volta(session()->pull('cadastro_volta'));
        session()->forget('cadastro_cpf');

        return redirect()->to($volta ?: route('comprador.ingressos'))->with('ok', 'Cadastro atualizado.');
    }

    /** Comprador do CPF; com o CPF só no cadastro único (pessoa), cria o comprador a partir dele. */
    private static function compradorDoCpf(string $cpf): ?Comprador
    {
        if (strlen($cpf) !== 11) {
            return null;
        }
        $d = InscricaoController::dadosDoCpf($cpf);
        if (! $d) {
            return null;
        }
        if ($d['comprador']) {
            return $d['comprador'];
        }
        if (empty($d['email'])) {
            return null;
        }

        return Comprador::where('email', $d['email'])->first()
            ?? Comprador::create(['nome' => $d['nome'], 'email' => $d['email'], 'cpf' => $cpf, 'telefone' => $d['telefone'] ?? null, 'senha_hash' => null]);
    }

    /** Só caminho interno (evita redirecionar para outro site). */
    private static function volta(?string $volta): ?string
    {
        return $volta && str_starts_with($volta, '/') && ! str_starts_with($volta, '//') ? $volta : null;
    }
}
