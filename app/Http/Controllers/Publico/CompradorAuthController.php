<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Comprador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Conta do comprador ("Meus ingressos"). Guard próprio (comprador), separado do produtor.
 * O acesso por link no e-mail (regras, seção 9) entra quando houver e-mail transacional;
 * por enquanto o comprador cria uma senha.
 */
class CompradorAuthController extends Controller
{
    public function entrar()
    {
        return view('comprador.entrar');
    }

    public function autenticar(Request $request)
    {
        $dados = $request->validate(['email' => ['required', 'email:filter'], 'senha' => ['required', 'string']]);
        $email = mb_strtolower(trim($dados['email']));
        $chave = 'comprador-login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($chave, 5)) {
            $min = (int) ceil(RateLimiter::availableIn($chave) / 60);
            throw ValidationException::withMessages(['email' => "Muitas tentativas. Tente de novo em {$min} min."]);
        }

        $comprador = Comprador::where('email', $email)->whereNull('anonimizado_em')->first();
        if (! $comprador || ! $comprador->senha_hash || ! Hash::check($dados['senha'], $comprador->senha_hash)) {
            RateLimiter::hit($chave, 15 * 60);
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        RateLimiter::clear($chave);
        Auth::guard('comprador')->login($comprador);
        session(['comprador_verificado' => $comprador->id]); // entrou com e-mail e senha: pode editar Meus dados
        $request->session()->regenerate();

        return redirect()->intended(route('comprador.ingressos'));
    }

    public function criar()
    {
        return view('comprador.criar');
    }

    public function registrar(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $dados = $request->validate(self::regrasConta());

        $comprador = Comprador::create([
            'nome' => $dados['nome'], 'email' => $dados['email'],
            'telefone' => preg_replace('/\D/', '', (string) ($dados['telefone'] ?? '')) ?: null,
            'senha_hash' => Hash::make($dados['senha']),
        ]);
        Auth::guard('comprador')->login($comprador);
        $request->session()->regenerate();

        return redirect()->intended(route('comprador.ingressos'))->with('ok', 'Conta criada.');
    }

    public function sair(Request $request)
    {
        // Só o comprador sai; uma sessão de produtor no mesmo navegador continua.
        Auth::guard('comprador')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('inicio')->with('ok', 'Você saiu de Meus ingressos.');
    }

    /** Regras de criação de conta, usadas também na inscrição. */
    public static function regrasConta(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:filter', 'max:150', Rule::unique('comprador', 'email')],
            'telefone' => ['nullable', 'string', 'max:20'],
            'senha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }
}
