<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Organizador;
use App\Support\Auditoria;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Dados da organização e da página pública (foradafila.com.br/p/<slug>). */
class OrganizacaoController extends Controller
{
    public function edit(Tenant $tenant)
    {
        Gate::authorize('organizador.editar');

        return view('painel.organizacao.edit', ['org' => $tenant->organizador()]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        Gate::authorize('organizador.editar');
        $org = $tenant->organizador();
        $request->merge([
            'slug' => \Illuminate\Support\Str::slug((string) $request->input('slug')),
            'cep' => preg_replace('/\D/', '', (string) $request->input('cep')) ?: null,
            'whatsapp' => preg_replace('/\D/', '', (string) $request->input('whatsapp')) ?: null,
            'instagram' => ltrim((string) $request->input('instagram'), '@') ?: null,
        ]);

        $dados = $request->validate([
            'razao_social' => ['required', 'string', 'max:150'],
            'nome_fantasia' => ['nullable', 'string', 'max:150'],
            'slug' => ['required', 'string', 'min:3', 'max:80', Rule::unique('organizador', 'slug')->ignore($org->id)],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'site' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._]+$/'],
            'whatsapp' => ['nullable', 'string', 'min:10', 'max:13'],
            'email_contato' => ['required', 'email', 'max:150'],
            'email_financeiro' => ['required', 'email', 'max:150'],
            'cep' => ['nullable', 'digits:8'],
            'logradouro' => ['nullable', 'string', 'max:150'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:60'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'uf' => ['nullable', 'string', 'size:2'],
        ], ['slug.unique' => 'Este endereço já está em uso por outra organização.'], [
            'slug' => 'endereço da página', 'email_contato' => 'e-mail de atendimento', 'email_financeiro' => 'e-mail financeiro',
        ]);
        $dados['uf'] = isset($dados['uf']) ? strtoupper($dados['uf']) : null;

        $antes = $org->getAttributes();
        Organizador::whereKey($org->id)->first()->update($dados);
        Auditoria::registrar('organizador.editar', $org->fresh(), $antes);

        return back()->with('ok', 'Dados da organização salvos.');
    }
}
