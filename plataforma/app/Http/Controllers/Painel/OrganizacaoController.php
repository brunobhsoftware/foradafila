<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Organizador;
use App\Rules\DocumentoValido;
use App\Support\Auditoria;
use App\Support\Documento;
use App\Support\Esquema;
use App\Support\Logomarca;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Dados da organização: identificação, representante legal, dados bancários, logomarca,
 * página pública e endereço. A chave de acesso da API fica bloqueada até existir o token de integração.
 */
class OrganizacaoController extends Controller
{
    /** Bancos mais usados, para sugerir no campo (o produtor pode digitar outro). */
    public const BANCOS = [
        '001 - Banco do Brasil', '033 - Santander', '041 - Banrisul', '070 - BRB', '077 - Banco Inter', '104 - Caixa Econômica Federal',
        '197 - Stone', '208 - BTG Pactual', '212 - Banco Original', '237 - Bradesco', '260 - Nubank', '290 - PagBank (PagSeguro)',
        '323 - Mercado Pago', '336 - C6 Bank', '341 - Itaú Unibanco', '380 - PicPay', '403 - Cora', '422 - Safra',
        '655 - Banco Votorantim', '748 - Sicredi', '756 - Sicoob',
    ];

    public function edit(Tenant $tenant)
    {
        Gate::authorize('organizador.editar');

        return view('painel.organizacao.edit', [
            'org' => Organizador::findOrFail($tenant->id()),
            'temV004' => Esquema::temV004(),
            'temV010' => Esquema::temV010(),
            'exemploTaxa' => Esquema::temV008() ? \App\Support\MotorTaxas::calcular(Organizador::findOrFail($tenant->id()), 'ingresso_pago', 50) : null,
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        Gate::authorize('organizador.editar');
        $org = Organizador::findOrFail($tenant->id());
        $v004 = Esquema::temV004();
        $request->merge([
            'slug' => Str::slug((string) $request->input('slug')),
            'cep' => preg_replace('/\D/', '', (string) $request->input('cep')) ?: null,
            'whatsapp' => preg_replace('/\D/', '', (string) $request->input('whatsapp')) ?: null,
            'instagram' => ltrim((string) $request->input('instagram'), '@') ?: null,
            'representante_cpf' => Documento::digitos($request->input('representante_cpf')) ?: null,
        ]);

        $regras = [
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
            'logo' => Logomarca::regras(),
        ];
        if ($v004) {
            $regras += [
                'representante_nome' => ['required', 'string', 'max:150'],
                'representante_cpf' => ['required', new DocumentoValido('PF')],
                // Dados bancários são opcionais; se um for preenchido, os quatro são pedidos juntos.
                'banco' => ['nullable', 'required_with:agencia,conta,favorecido', 'string', 'max:80'],
                'agencia' => ['nullable', 'required_with:banco,conta', 'string', 'max:10', 'regex:/^[0-9]{1,6}(-?[0-9Xx])?$/'],
                'conta' => ['nullable', 'required_with:banco,agencia', 'string', 'max:20', 'regex:/^[0-9]{1,15}-?[0-9Xx]?$/'],
                'favorecido' => ['nullable', 'required_with:banco,agencia,conta', 'string', 'max:150'],
            ];
        }

        $dados = $request->validate($regras, [
            'slug.unique' => 'Este endereço já está em uso por outra organização.',
            'agencia.regex' => 'Agência: use só números, com o dígito se houver (ex.: 1234 ou 1234-5).',
            'conta.regex' => 'Conta: use só números e o dígito (ex.: 12345-6).',
            'representante_cpf.required' => 'Informe o CPF do representante legal.',
            'banco.required_with' => 'Para cadastrar a conta bancária, informe também o banco.',
            'agencia.required_with' => 'Para cadastrar a conta bancária, informe também a agência.',
            'conta.required_with' => 'Para cadastrar a conta bancária, informe também a conta.',
            'favorecido.required_with' => 'Para cadastrar a conta bancária, informe também o favorecido.',
            'logo.max' => 'A logomarca pode ter no máximo 2 MB.',
            'logo.uploaded' => 'A logomarca pode ter no máximo 2 MB.',
            'logo.mimes' => 'Envie a logomarca em JPEG, GIF ou PNG.',
            'logo.mimetypes' => 'Envie a logomarca em JPEG, GIF ou PNG.',
        ], [
            'slug' => 'endereço da página', 'email_contato' => 'e-mail de atendimento', 'email_financeiro' => 'e-mail financeiro',
            'representante_nome' => 'representante legal', 'representante_cpf' => 'CPF do representante legal',
            'banco' => 'banco', 'agencia' => 'agência', 'conta' => 'conta', 'favorecido' => 'favorecido', 'logo' => 'logomarca',
        ]);
        unset($dados['logo']);
        $dados['uf'] = isset($dados['uf']) ? strtoupper($dados['uf']) : null;
        if (Esquema::temV010()) {
            $dados['taxa_comprador'] = $request->input('taxa_comprador') === '1';
        }
        if ($v004) {
            $dados['mostrar_eventos_passados'] = $request->boolean('mostrar_eventos_passados');
            $dados['agencia'] = isset($dados['agencia']) ? strtoupper($dados['agencia']) : null;
            $dados['conta'] = isset($dados['conta']) ? strtoupper($dados['conta']) : null;
        }

        $antes = $org->getAttributes();
        DB::transaction(function () use ($org, $dados, $request) {
            $org->fill($dados);
            if ($request->hasFile('logo')) {
                $org->logo_url = Logomarca::salvar($org, $request->file('logo'));
            } elseif ($request->boolean('remover_logo')) {
                Logomarca::remover($org);
                $org->logo_url = null;
            }
            $org->save();
        });
        Auditoria::registrar('organizador.editar', $org, $antes);

        return back()->with('ok', 'Dados da organização salvos.');
    }
}
