<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\OrganizadorUsuario;
use App\Models\PerfilAcesso;
use App\Models\Usuario;
use App\Support\Auditoria;
use App\Support\Notificacoes;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Equipe do organizador (regras, seção 6): convite por link com validade de 72 h,
 * um perfil por pessoa, opção de limitar a eventos específicos, e o último Dono
 * ativo nunca pode ser removido nem rebaixado.
 */
class EquipeController extends Controller
{
    public function index(Tenant $tenant)
    {
        Gate::authorize('equipe.gerenciar');

        $vinculos = OrganizadorUsuario::with(['usuario', 'perfil', 'eventos'])
            ->where('status', '<>', 'removido')->orderByRaw("FIELD(status, 'ativo', 'convidado', 'suspenso')")->orderBy('id')->get();
        $perfis = $this->perfisAtribuiveis($tenant);
        $eventos = Evento::whereNotIn('status', ['cancelado'])->orderBy('data_inicio')->pluck('titulo', 'id');

        return view('painel.equipe.index', compact('vinculos', 'perfis', 'eventos'));
    }

    public function store(Request $request, Tenant $tenant)
    {
        Gate::authorize('equipe.gerenciar');
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $dados = $this->validar($request, $tenant) + $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
        ]);

        $usuario = Usuario::firstOrCreate(['email' => $dados['email']], ['nome' => $dados['nome']]);
        if (OrganizadorUsuario::where('usuario_id', $usuario->id)->where('status', '<>', 'removido')->exists()) {
            throw ValidationException::withMessages(['email' => 'Esta pessoa já faz parte da equipe.']);
        }

        $token = Str::random(48);
        $vinculo = DB::transaction(function () use ($usuario, $dados, $token, $request) {
            $vinculo = OrganizadorUsuario::updateOrCreate(['usuario_id' => $usuario->id], [
                'perfil_id' => $dados['perfil_id'],
                'todos_eventos' => $dados['todos_eventos'],
                'status' => 'convidado',
                'convite_token_hash' => hash('sha256', $token),
                'convite_expira_em' => now()->addHours(72),
                'convidado_por' => $request->user()->id,
            ]);
            $vinculo->eventos()->sync($dados['todos_eventos'] ? [] : $dados['eventos']);
            Auditoria::registrar('equipe.convidar', $vinculo);

            return $vinculo;
        });

        $url = route('convite', $token);
        Notificacoes::conviteEquipe($usuario, $tenant->organizador(), $vinculo->perfil->nome, $url, $request->user()->nome);

        return back()->with('ok', "Convite enviado por e-mail para {$usuario->nome} ({$usuario->email}).")
            ->with('link_convite', ['nome' => $usuario->nome, 'url' => $url]);
    }

    public function update(Request $request, OrganizadorUsuario $vinculo, Tenant $tenant)
    {
        Gate::authorize('equipe.gerenciar');
        $dados = $this->validar($request, $tenant) + $request->validate([
            'status' => ['required', Rule::in($vinculo->status === 'convidado' ? ['convidado'] : ['ativo', 'suspenso'])],
        ]);

        $novoPerfil = PerfilAcesso::findOrFail($dados['perfil_id']);
        $deixaDeSerDonoAtivo = $vinculo->ehDono() && $vinculo->status === 'ativo'
            && ($novoPerfil->codigo !== 'dono' || $novoPerfil->organizador_id !== null || $dados['status'] !== 'ativo');
        if ($deixaDeSerDonoAtivo) {
            $this->exigirOutroDono($vinculo);
        }

        $antes = $vinculo->getAttributes();
        DB::transaction(function () use ($vinculo, $dados) {
            $vinculo->update(['perfil_id' => $dados['perfil_id'], 'todos_eventos' => $dados['todos_eventos'], 'status' => $dados['status']]);
            $vinculo->eventos()->sync($dados['todos_eventos'] ? [] : $dados['eventos']);
        });
        Auditoria::registrar('equipe.alterar', $vinculo, $antes);

        return back()->with('ok', 'Acesso de '.$vinculo->usuario->nome.' atualizado.');
    }

    public function reenviar(Request $request, OrganizadorUsuario $vinculo, Tenant $tenant)
    {
        Gate::authorize('equipe.gerenciar');
        abort_unless($vinculo->status === 'convidado', 422);

        $token = Str::random(48);
        $vinculo->update(['convite_token_hash' => hash('sha256', $token), 'convite_expira_em' => now()->addHours(72)]);
        Auditoria::registrar('equipe.reenviar_convite', $vinculo);

        $url = route('convite', $token);
        Notificacoes::conviteEquipe($vinculo->usuario, $tenant->organizador(), $vinculo->perfil->nome, $url, $request->user()->nome);

        return back()->with('ok', 'Novo convite enviado por e-mail (o link anterior deixou de valer).')
            ->with('link_convite', ['nome' => $vinculo->usuario->nome, 'url' => $url]);
    }

    public function destroy(OrganizadorUsuario $vinculo)
    {
        Gate::authorize('equipe.gerenciar');
        if ($vinculo->ehDono() && $vinculo->status === 'ativo') {
            $this->exigirOutroDono($vinculo);
        }

        $antes = $vinculo->getAttributes();
        $vinculo->update(['status' => 'removido', 'convite_token_hash' => null, 'convite_expira_em' => null]);
        Auditoria::registrar('equipe.remover', $vinculo, $antes);

        return back()->with('ok', $vinculo->usuario->nome.' foi removido(a) da equipe.');
    }

    private function exigirOutroDono(OrganizadorUsuario $vinculo): void
    {
        $outros = OrganizadorUsuario::where('id', '<>', $vinculo->id)->where('status', 'ativo')
            ->where('perfil_id', PerfilAcesso::dono()->id)->count();
        if ($outros === 0) {
            throw ValidationException::withMessages(['perfil_id' => 'A organização precisa ter pelo menos um Produtor ativo (perfil de acesso total). Promova outra pessoa a Produtor antes.']);
        }
    }

    private function perfisAtribuiveis(Tenant $tenant)
    {
        return PerfilAcesso::disponiveisPara($tenant->id())->get()
            ->reject(fn ($p) => $p->codigo === 'dono' && $p->ehDoSistema() && ! $tenant->ehDono());
    }

    private function validar(Request $request, Tenant $tenant): array
    {
        $perfis = $this->perfisAtribuiveis($tenant)->pluck('id')->all();
        $dados = $request->validate([
            'perfil_id' => ['required', Rule::in($perfis)],
            'eventos' => ['array'],
            'eventos.*' => [Rule::exists('evento', 'id')->where('organizador_id', $tenant->id())],
        ], ['perfil_id.in' => 'Perfil inválido ou que você não pode atribuir.'], ['perfil_id' => 'perfil']);
        $dados['todos_eventos'] = $request->input('alcance', 'todos') === 'todos';
        $dados['eventos'] ??= [];

        if (! $dados['todos_eventos'] && empty($dados['eventos'])) {
            throw ValidationException::withMessages(['eventos' => 'Escolha pelo menos um evento ou marque “Todos os eventos”.']);
        }

        return $dados;
    }
}
