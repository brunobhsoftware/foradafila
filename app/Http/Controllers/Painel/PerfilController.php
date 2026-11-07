<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\PerfilAcesso;
use App\Models\Permissao;
use App\Support\Auditoria;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Níveis de acesso: os 6 perfis do sistema (fixos) e perfis próprios do organizador. */
class PerfilController extends Controller
{
    public function index(Tenant $tenant)
    {
        Gate::authorize('equipe.gerenciar');
        $perfis = PerfilAcesso::disponiveisPara($tenant->id())->with('permissoes')->get();
        $permissoes = Permissao::orderBy('id')->get()->groupBy('modulo');

        return view('painel.perfis.index', compact('perfis', 'permissoes'));
    }

    public function create(Request $request, Tenant $tenant)
    {
        Gate::authorize('equipe.gerenciar');
        $base = $request->filled('base')
            ? PerfilAcesso::disponiveisPara($tenant->id())->with('permissoes')->findOrFail($request->input('base'))
            : null;
        $perfil = new PerfilAcesso(['nome' => $base ? $base->nome.' (personalizado)' : '', 'descricao' => $base?->descricao]);
        $marcadas = $base ? $base->permissoes->pluck('id')->all() : [];

        return view('painel.perfis.form', ['perfil' => $perfil, 'marcadas' => $marcadas] + $this->permissoes());
    }

    public function store(Request $request, Tenant $tenant)
    {
        Gate::authorize('equipe.gerenciar');
        $dados = $this->validar($request);

        $perfil = DB::transaction(function () use ($dados, $tenant) {
            $codigo = Str::limit(Str::slug($dados['nome'], '_'), 24, '') ?: 'perfil';
            $sufixo = '';
            $n = 2;
            while (PerfilAcesso::where('organizador_id', $tenant->id())->where('codigo', $codigo.$sufixo)->exists()) {
                $sufixo = '_'.$n++;
            }
            $perfil = PerfilAcesso::create([
                'organizador_id' => $tenant->id(), 'codigo' => $codigo.$sufixo,
                'nome' => $dados['nome'], 'descricao' => $dados['descricao'] ?? null, 'editavel' => true,
            ]);
            $perfil->permissoes()->sync($dados['permissoes']);
            Auditoria::registrar('perfil.criar', $perfil, null, $perfil->getAttributes() + ['permissoes' => $dados['permissoes']]);

            return $perfil;
        });

        return redirect()->route('perfis.index')->with('ok', "Perfil “{$perfil->nome}” criado.");
    }

    public function edit(PerfilAcesso $perfil, Tenant $tenant)
    {
        Gate::authorize('equipe.gerenciar');
        $this->exigirProprio($perfil, $tenant);

        return view('painel.perfis.form', [
            'perfil' => $perfil, 'marcadas' => $perfil->permissoes()->pluck('permissao.id')->all(),
        ] + $this->permissoes());
    }

    public function update(Request $request, PerfilAcesso $perfil, Tenant $tenant)
    {
        Gate::authorize('equipe.gerenciar');
        $this->exigirProprio($perfil, $tenant);
        $dados = $this->validar($request);

        $antes = $perfil->getAttributes() + ['permissoes' => $perfil->permissoes()->pluck('permissao.id')->all()];
        DB::transaction(function () use ($perfil, $dados) {
            $perfil->update(['nome' => $dados['nome'], 'descricao' => $dados['descricao'] ?? null]);
            $perfil->permissoes()->sync($dados['permissoes']);
        });
        Auditoria::registrar('perfil.editar', $perfil, $antes, $perfil->getAttributes() + ['permissoes' => $dados['permissoes']]);

        return redirect()->route('perfis.index')->with('ok', "Perfil “{$perfil->nome}” atualizado.");
    }

    /** Perfis do sistema não se editam: o organizador cria um próprio a partir deles. */
    private function exigirProprio(PerfilAcesso $perfil, Tenant $tenant): void
    {
        abort_unless($perfil->organizador_id === $tenant->id() && $perfil->editavel, 404);
    }

    private function permissoes(): array
    {
        return ['permissoes' => Permissao::orderBy('id')->get()->groupBy('modulo')];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:60'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'permissoes' => ['required', 'array', 'min:1'],
            'permissoes.*' => ['integer', 'exists:permissao,id'],
        ], ['permissoes.required' => 'Marque pelo menos uma permissão.']);
    }
}
