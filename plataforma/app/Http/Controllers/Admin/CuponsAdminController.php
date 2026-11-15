<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Auditoria;
use App\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Administração → Cupons de cadastro: promoções aplicadas no momento do registro de novos produtores. */
class CuponsAdminController extends Controller
{
    public function index()
    {
        abort_unless(Esquema::temV019(), 404);

        return view('admin.cupons.index', [
            'cupons' => DB::table('cupom_cadastro')->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Esquema::temV019(), 404);
        $d = $this->validar($request);
        DB::table('cupom_cadastro')->insert([
            'uuid'           => (string) Str::uuid(),
            'codigo'         => strtoupper(trim($d['codigo'])),
            'nome'           => $d['nome'],
            'isento'         => isset($d['isento']) ? 1 : 0,
            'percentual'     => $d['percentual'] !== '' ? $d['percentual'] : null,
            'valor_fixo'     => $d['valor_fixo'] !== '' ? $d['valor_fixo'] : null,
            'valor_minimo'   => $d['valor_minimo'] !== '' ? $d['valor_minimo'] : null,
            'meses_validade' => $d['meses_validade'] !== '' ? $d['meses_validade'] : null,
            'limite_usos'    => $d['limite_usos'] !== '' ? $d['limite_usos'] : null,
            'observacao'     => $d['observacao'] ?: null,
            'ativo'          => 1,
            'criado_por'     => auth()->id(),
            'criado_em'      => now(),
        ]);
        Auditoria::registrar('admin.cupom_cadastro.criar', null, null, ['codigo' => strtoupper(trim($d['codigo']))]);

        return redirect()->route('admin.cupons')->with('ok', 'Cupom '.strtoupper(trim($d['codigo'])).' criado.');
    }

    public function update(Request $request, int $id)
    {
        abort_unless(Esquema::temV019(), 404);
        $cupom = DB::table('cupom_cadastro')->findOrFail($id);
        $d = $this->validar($request, $id);
        DB::table('cupom_cadastro')->where('id', $id)->update([
            'codigo'         => strtoupper(trim($d['codigo'])),
            'nome'           => $d['nome'],
            'isento'         => isset($d['isento']) ? 1 : 0,
            'percentual'     => $d['percentual'] !== '' ? $d['percentual'] : null,
            'valor_fixo'     => $d['valor_fixo'] !== '' ? $d['valor_fixo'] : null,
            'valor_minimo'   => $d['valor_minimo'] !== '' ? $d['valor_minimo'] : null,
            'meses_validade' => $d['meses_validade'] !== '' ? $d['meses_validade'] : null,
            'limite_usos'    => $d['limite_usos'] !== '' ? $d['limite_usos'] : null,
            'observacao'     => $d['observacao'] ?: null,
        ]);
        Auditoria::registrar('admin.cupom_cadastro.editar', null, null, ['id' => $id, 'codigo' => strtoupper(trim($d['codigo']))]);

        return redirect()->route('admin.cupons')->with('ok', 'Cupom atualizado.');
    }

    public function alternar(int $id)
    {
        abort_unless(Esquema::temV019(), 404);
        $cupom = DB::table('cupom_cadastro')->where('id', $id)->first() ?? abort(404);
        $novo = $cupom->ativo ? 0 : 1;
        DB::table('cupom_cadastro')->where('id', $id)->update(['ativo' => $novo]);
        Auditoria::registrar('admin.cupom_cadastro.'.($novo ? 'ativar' : 'desativar'), null, null, ['id' => $id]);

        return back()->with('ok', $novo ? 'Cupom ativado.' : 'Cupom desativado.');
    }

    private function validar(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'codigo'         => ['required', 'string', 'max:50', 'alpha_num',
                \Illuminate\Validation\Rule::unique('cupom_cadastro', 'codigo')->ignore($ignorarId)->withoutTrashed()],
            'nome'           => ['required', 'string', 'max:150'],
            'isento'         => ['nullable', 'boolean'],
            'percentual'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valor_fixo'     => ['nullable', 'numeric', 'min:0'],
            'valor_minimo'   => ['nullable', 'numeric', 'min:0'],
            'meses_validade' => ['nullable', 'integer', 'min:1'],
            'limite_usos'    => ['nullable', 'integer', 'min:1'],
            'observacao'     => ['nullable', 'string', 'max:255'],
        ], [
            'codigo.alpha_num' => 'O código deve conter apenas letras e números (sem espaço ou símbolo).',
            'codigo.unique'    => 'Já existe um cupom com este código.',
        ], ['codigo' => 'código', 'nome' => 'nome', 'percentual' => 'percentual', 'valor_fixo' => 'valor fixo',
            'valor_minimo' => 'valor mínimo', 'meses_validade' => 'meses de validade', 'limite_usos' => 'limite de usos']);
    }

    /** Verifica e retorna um cupom válido para uso no cadastro (chamado pelo CadastroController). */
    public static function conferir(string $codigo): ?object
    {
        if (! Esquema::temV019()) {
            return null;
        }
        $c = DB::table('cupom_cadastro')->where('codigo', strtoupper(trim($codigo)))->first();
        if (! $c || ! $c->ativo) {
            return null;
        }
        if ($c->limite_usos !== null && $c->usos >= $c->limite_usos) {
            return null;
        }

        return $c;
    }
}
