<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organizador;
use App\Models\OrganizadorPlano;
use App\Models\OrganizadorUsuario;
use App\Models\PerfilAcesso;
use App\Models\Plano;
use App\Models\Termo;
use App\Models\TermoAceite;
use App\Models\Usuario;
use App\Rules\DocumentoValido;
use App\Support\Auditoria;
use App\Support\Documento;
use App\Support\Notificacoes;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Cadastro do produtor: cria, numa transação só, o usuário responsável,
 * o organizador, o vínculo como Dono, o plano inicial e o aceite dos termos.
 */
class CadastroController extends Controller
{
    public function create()
    {
        $termos = Termo::vigente()->whereIn('tipo', Termo::DO_ORGANIZADOR)->get()->keyBy('tipo');

        return view('auth.cadastro', compact('termos'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'documento' => Documento::digitos($request->input('documento')),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('usuario', 'email')],
            'telefone' => ['required', 'string', 'max:20'],
            'senha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'tipo_pessoa' => ['required', Rule::in(['PF', 'PJ'])],
            'documento' => ['required', new DocumentoValido($request->input('tipo_pessoa')), Rule::unique('organizador', 'documento')],
            'razao_social' => ['required', 'string', 'max:150'],
            'nome_fantasia' => ['nullable', 'string', 'max:150'],
            'email_contato' => ['required', 'email', 'max:150'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'uf' => ['nullable', 'string', 'size:2'],
            'aceites' => ['required', 'array'],
            'aceites.*' => ['accepted'],
        ], [
            'email.unique' => 'Já existe uma conta com este e-mail. Entre com ele ou use outro.',
            'documento.unique' => 'Este CPF/CNPJ já tem cadastro no Fora da Fila.',
        ]);

        $termos = Termo::vigente()->whereIn('tipo', Termo::DO_ORGANIZADOR)->get();
        $faltando = $termos->reject(fn ($t) => isset($dados['aceites'][$t->tipo]));
        if ($termos->isEmpty() || $faltando->isNotEmpty()) {
            return back()->withInput()->withErrors(['aceites' => 'É preciso aceitar todos os termos para criar a conta.']);
        }

        $usuario = DB::transaction(function () use ($dados, $termos, $request) {
            $usuario = Usuario::create([
                'nome' => $dados['nome'],
                'email' => $dados['email'],
                'telefone' => $dados['telefone'],
                'senha_hash' => Hash::make($dados['senha']),
            ]);

            $organizador = Organizador::create([
                'tipo_pessoa' => $dados['tipo_pessoa'],
                'documento' => $dados['documento'],
                'razao_social' => $dados['razao_social'],
                'nome_fantasia' => $dados['nome_fantasia'] ?? null,
                'slug' => Organizador::slugDisponivel($dados['nome_fantasia'] ?: $dados['razao_social']),
                'email_contato' => $dados['email_contato'],
                'email_financeiro' => $dados['email'],
                'whatsapp' => Documento::digitos($dados['telefone']),
                'cidade' => $dados['cidade'] ?? null,
                'uf' => isset($dados['uf']) ? strtoupper($dados['uf']) : null,
                // Projeto piloto: a conta já nasce ativa. Quando o e-mail transacional estiver
                // configurado, passa a nascer 'pendente' até confirmar o e-mail (regras, seção 5).
                'situacao' => 'ativo',
            ]);

            app(Tenant::class)->definir($organizador);

            OrganizadorUsuario::create([
                'usuario_id' => $usuario->id,
                'perfil_id' => PerfilAcesso::dono()->id,
                'todos_eventos' => true,
                'status' => 'ativo',
            ]);

            if ($plano = Plano::where('ativo', 1)->where('publico', 1)->orderBy('id')->first()) {
                OrganizadorPlano::create(['plano_id' => $plano->id, 'inicio' => now()->toDateString()]);
            }

            foreach ($termos as $termo) {
                TermoAceite::create([
                    'termo_id' => $termo->id,
                    'organizador_id' => $organizador->id,
                    'usuario_id' => $usuario->id,
                    'ip' => $request->ip() ?? '0.0.0.0',
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                ]);
            }

            Auditoria::registrar('organizador.cadastrar', $organizador);

            return $usuario;
        });

        Auth::login($usuario);
        $request->session()->regenerate();
        $request->session()->put('organizador_id', app(Tenant::class)->id());
        Notificacoes::boasVindasProdutor($usuario, app(Tenant::class)->organizador());

        return redirect()->route('painel')->with('ok', 'Conta criada! Bem-vindo ao Fora da Fila.');
    }
}
