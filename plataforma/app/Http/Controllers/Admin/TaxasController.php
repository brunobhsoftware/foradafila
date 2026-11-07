<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Support\Auditoria;
use App\Support\MotorTaxas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Administração: taxas padrão da plataforma e faixas por volume (motor de taxas). */
class TaxasController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    /** Sem o V008 (tabelas de taxas e de impersonação), mostra o aviso em vez de erro. */
    public static function middleware(): array
    {
        return [new \Illuminate\Routing\Controllers\Middleware(
            fn ($request, $next) => \App\Support\Esquema::temV008() ? $next($request) : response()->view('admin.precisa-v008'))];
    }

    public function index()
    {
        $regras = DB::table('taxa_regra')->orderBy('operacao')->orderBy('volume_min')->get()->groupBy('operacao');
        $especificas = DB::table('taxa_organizador as t')->join('organizador as o', 'o.id', '=', 't.organizador_id')
            ->where(fn ($q) => $q->whereNull('t.fim')->orWhere('t.fim', '>=', now()->toDateString()))
            ->select('t.*', 'o.razao_social', 'o.nome_fantasia', 'o.uuid as org_uuid')->orderBy('o.razao_social')->get();

        return view('admin.taxas', compact('regras', 'especificas'));
    }

    public function salvar(Request $request, ?int $regra = null)
    {
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:80'],
            'operacao' => ['required', 'in:'.implode(',', array_keys(MotorTaxas::OPERACOES))],
            'percentual' => ['required', 'numeric', 'min:0', 'max:100'],
            'valor_fixo' => ['required', 'numeric', 'min:0', 'max:100000'],
            'valor_minimo' => ['required', 'numeric', 'min:0', 'max:100000'],
            'volume_min' => ['required', 'numeric', 'min:0'],
            'volume_max' => ['nullable', 'numeric', 'gt:volume_min'],
            'carencia_dias' => ['required', 'integer', 'min:0', 'max:3650'],
            'ativo' => ['nullable', 'boolean'],
        ], ['volume_max.gt' => 'O fim da faixa precisa ser maior que o início.'],
            ['valor_fixo' => 'tarifa fixa', 'valor_minimo' => 'taxa mínima', 'volume_min' => 'início da faixa', 'volume_max' => 'fim da faixa', 'carencia_dias' => 'carência']);
        $d['ativo'] = (bool) ($d['ativo'] ?? false);

        // Faixas da mesma operação não podem se sobrepor (entre regras ativas).
        if ($d['ativo']) {
            $max = $d['volume_max'] ?? PHP_FLOAT_MAX;
            $conflito = DB::table('taxa_regra')->where('operacao', $d['operacao'])->where('ativo', 1)
                ->when($regra, fn ($q) => $q->where('id', '<>', $regra))
                ->where('volume_min', '<', $max)
                ->where(fn ($q) => $q->whereNull('volume_max')->orWhere('volume_max', '>', $d['volume_min']))->first();
            if ($conflito) {
                return back()->withInput()->withErrors(['volume_min' => "A faixa se sobrepõe à regra ativa “{$conflito->nome}”. Ajuste os volumes ou desative a outra regra."]);
            }
        }

        if ($regra) {
            abort_unless(DB::table('taxa_regra')->where('id', $regra)->exists(), 404);
            DB::table('taxa_regra')->where('id', $regra)->update($d);
        } else {
            $regra = DB::table('taxa_regra')->insertGetId($d);
        }
        Auditoria::registrar('plataforma.taxa_regra_salvar', $request->user(), null, ['taxa_regra_id' => $regra] + $d);

        return redirect()->route('admin.taxas')->with('ok', "Regra “{$d['nome']}” salva.");
    }
}
