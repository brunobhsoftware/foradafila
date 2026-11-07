<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organizador;
use App\Models\OrganizadorUsuario;
use App\Support\Auditoria;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Aceite de convite para a equipe: o link leva um token (só o hash fica no banco)
 * válido por 72 h. Quem ainda não tem senha cria uma; quem já tem confirma a sua.
 */
class ConviteController extends Controller
{
    private function vinculo(string $token): OrganizadorUsuario
    {
        $vinculo = OrganizadorUsuario::withoutGlobalScope('organizador')
            ->with(['usuario', 'perfil'])
            ->where('convite_token_hash', hash('sha256', $token))
            ->where('status', 'convidado')
            ->first();

        abort_if(! $vinculo || ! $vinculo->convite_expira_em || $vinculo->convite_expira_em->isPast(), 404);

        return $vinculo;
    }

    public function show(string $token)
    {
        $vinculo = $this->vinculo($token);
        $organizador = Organizador::findOrFail($vinculo->organizador_id);

        return view('auth.convite', compact('vinculo', 'organizador', 'token'));
    }

    public function store(Request $request, string $token)
    {
        $vinculo = $this->vinculo($token);
        $usuario = $vinculo->usuario;

        if ($usuario->senha_hash) {
            $request->validate(['senha' => ['required', 'string']]);
            if (! Hash::check($request->input('senha'), $usuario->senha_hash)) {
                return back()->withErrors(['senha' => 'Senha incorreta.']);
            }
        } else {
            $dados = $request->validate([
                'nome' => ['required', 'string', 'max:150'],
                'telefone' => ['nullable', 'string', 'max:20'],
                'senha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            ]);
            $usuario->forceFill([
                'nome' => $dados['nome'],
                'telefone' => $dados['telefone'] ?? $usuario->telefone,
                'senha_hash' => Hash::make($dados['senha']),
                'email_verificado_em' => now(), // o link chegou pelo e-mail/canal informado pelo organizador
            ])->save();
        }

        $vinculo->forceFill(['status' => 'ativo', 'convite_token_hash' => null, 'convite_expira_em' => null])->save();

        app(Tenant::class)->definir(Organizador::findOrFail($vinculo->organizador_id), $vinculo);
        Auth::login($usuario);
        Auditoria::registrar('equipe.aceitar_convite', $vinculo);

        $request->session()->regenerate();
        $request->session()->put('organizador_id', $vinculo->organizador_id);

        return redirect()->route('painel')->with('ok', 'Convite aceito. Bem-vindo à equipe!');
    }
}
