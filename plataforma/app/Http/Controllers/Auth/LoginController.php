<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/** Login com bloqueio: 5 senhas erradas bloqueiam por 15 minutos (regras, seção 6). */
class LoginController extends Controller
{
    private const MAX_TENTATIVAS = 5;

    private const BLOQUEIO_MINUTOS = 15;

    public function create()
    {
        return view('auth.entrar');
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
        ]);

        $usuario = Usuario::where('email', mb_strtolower(trim($dados['email'])))->first();
        $erro = ['email' => 'E-mail ou senha incorretos.'];

        if (! $usuario || ! $usuario->ativo || ! $usuario->senha_hash) {
            return back()->withInput($request->only('email'))->withErrors($erro);
        }

        if ($usuario->bloqueado_ate && $usuario->bloqueado_ate->isFuture()) {
            $minutos = (int) ceil(now()->diffInSeconds($usuario->bloqueado_ate) / 60);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => "Acesso bloqueado por excesso de tentativas. Tente de novo em {$minutos} min."]);
        }

        if (! Hash::check($dados['senha'], $usuario->senha_hash)) {
            $usuario->tentativas_login++;
            if ($usuario->tentativas_login >= self::MAX_TENTATIVAS) {
                $usuario->bloqueado_ate = now()->addMinutes(self::BLOQUEIO_MINUTOS);
                $usuario->tentativas_login = 0;
            }
            $usuario->save();

            return back()->withInput($request->only('email'))->withErrors($erro);
        }

        $usuario->forceFill(['tentativas_login' => 0, 'bloqueado_ate' => null, 'ultimo_login_em' => now()])->save();

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->intended(route('painel'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('entrar')->with('ok', 'Você saiu da sua conta.');
    }
}
