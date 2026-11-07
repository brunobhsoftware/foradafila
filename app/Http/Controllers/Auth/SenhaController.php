<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Comprador;
use App\Models\Usuario;
use App\Support\Notificacoes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;

/**
 * "Esqueci a senha" para produtor (usuario) e comprador, sem tabela de tokens:
 * o link é uma URL assinada que vale 60 minutos e carrega uma impressão (HMAC) da
 * senha atual — trocou a senha, o link deixa de valer. A resposta é sempre a mesma,
 * exista ou não a conta, para não revelar quem tem cadastro.
 */
class SenhaController extends Controller
{
    private const AREAS = [
        'produtor' => ['modelo' => Usuario::class, 'nome' => 'área do produtor', 'login' => 'entrar'],
        'comprador' => ['modelo' => Comprador::class, 'nome' => 'Meus ingressos', 'login' => 'comprador.entrar'],
    ];

    public function pedir(string $area)
    {
        return view('auth.senha-pedir', ['area' => $area, 'info' => self::AREAS[$area]]);
    }

    public function enviar(Request $request, string $area)
    {
        $email = mb_strtolower(trim((string) $request->validate(['email' => ['required', 'email']])['email']));
        $modelo = self::AREAS[$area]['modelo'];
        $conta = $modelo::where('email', $email)->first();

        if ($conta && $conta->senha_hash && ($area !== 'produtor' || $conta->ativo)) {
            // Assinatura só do caminho (sem domínio/esquema): na Hostinger o HTTPS termina na CDN.
            $url = url(URL::temporarySignedRoute('senha.nova', now()->addMinutes(60), [
                'area' => $area, 'uuid' => $conta->uuid, 'h' => self::impressao($conta->senha_hash),
            ], absolute: false));
            Notificacoes::redefinirSenha($conta->nome, $conta->email, $url, self::AREAS[$area]['nome']);
        }

        return back()->with('ok', 'Se houver uma conta com este e-mail, enviamos um link para criar uma nova senha. Confira também a caixa de spam.');
    }

    public function nova(Request $request, string $area, string $uuid)
    {
        $this->conta($request, $area, $uuid);

        return view('auth.senha-nova', ['area' => $area, 'info' => self::AREAS[$area]]);
    }

    public function salvar(Request $request, string $area, string $uuid)
    {
        $conta = $this->conta($request, $area, $uuid);
        $dados = $request->validate(['senha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);

        $conta->senha_hash = Hash::make($dados['senha']);
        if ($conta instanceof Usuario) {
            $conta->tentativas_login = 0;
            $conta->bloqueado_ate = null;
        }
        $conta->save();

        return redirect()->route(self::AREAS[$area]['login'])->with('ok', 'Senha alterada. Entre com a nova senha.');
    }

    private function conta(Request $request, string $area, string $uuid)
    {
        abort_unless($request->hasValidSignature(absolute: false), 403, 'Link inválido ou expirado. Peça um novo.');
        $conta = self::AREAS[$area]['modelo']::where('uuid', $uuid)->firstOrFail();
        abort_unless($conta->senha_hash && hash_equals(self::impressao($conta->senha_hash), (string) $request->query('h')), 403,
            'Este link já foi usado ou expirou. Peça um novo.');

        return $conta;
    }

    private static function impressao(string $senhaHash): string
    {
        return substr(hash_hmac('sha256', $senhaHash, (string) config('app.key')), 0, 24);
    }
}
