<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Cupom;
use App\Models\Evento;
use App\Models\Lote;
use App\Support\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Divulgação → Cupons de desconto (por evento ou para todos os eventos), com limite de usos, validade e lotes. */
class CuponsController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('cupom.gerenciar');
        $eventos = Evento::visiveis()->orderByDesc('data_inicio')->get(['id', 'uuid', 'titulo']);
        $filtro = $request->query('evento');
        $eventoFiltro = $filtro ? $eventos->firstWhere('uuid', $filtro) : null;
        $cupons = Cupom::with(['evento', 'lotes'])
            ->when($eventoFiltro, fn ($q) => $q->where(fn ($w) => $w->where('evento_id', $eventoFiltro->id)->orWhereNull('evento_id')))
            ->orderByDesc('id')->get();
        $lotes = Lote::with('tipo')->whereIn('evento_id', $eventos->pluck('id'))->orderBy('evento_id')->orderBy('ordem')->get()->groupBy('evento_id');

        return view('painel.cupons.index', compact('cupons', 'eventos', 'eventoFiltro', 'lotes'));
    }

    public function store(Request $request)
    {
        Gate::authorize('cupom.gerenciar');
        [$dados, $lotes] = $this->validar($request);
        $cupom = DB::transaction(function () use ($dados, $lotes) {
            $c = Cupom::create($dados + ['criado_por' => auth()->id()]);
            $c->lotes()->sync($lotes);

            return $c;
        });
        Auditoria::registrar('cupom.criar', $cupom);

        return redirect()->route('cupons.index')->with('ok', "Cupom {$cupom->codigo} criado.");
    }

    public function update(Request $request, Cupom $cupom)
    {
        Gate::authorize('cupom.gerenciar');
        $antes = $cupom->getAttributes();
        [$dados, $lotes] = $this->validar($request, $cupom);
        DB::transaction(function () use ($cupom, $dados, $lotes) {
            $cupom->update($dados);
            $cupom->lotes()->sync($lotes);
        });
        Auditoria::registrar('cupom.editar', $cupom, $antes);

        return redirect()->route('cupons.index')->with('ok', "Cupom {$cupom->codigo} atualizado.");
    }

    public function alternar(Cupom $cupom)
    {
        Gate::authorize('cupom.gerenciar');
        $antes = $cupom->getAttributes();
        $cupom->update(['ativo' => ! $cupom->ativo]);
        Auditoria::registrar($cupom->ativo ? 'cupom.ativar' : 'cupom.desativar', $cupom, $antes);

        return back()->with('ok', "Cupom {$cupom->codigo} ".($cupom->ativo ? 'ativado.' : 'desativado.'));
    }

    private function validar(Request $request, ?Cupom $cupom = null): array
    {
        $request->merge(['codigo' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('codigo')))]);
        $eventos = Evento::visiveis()->pluck('id', 'uuid');
        $d = $request->validate([
            'codigo' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('cupom', 'codigo')->where('organizador_id', app(\App\Support\Tenant::class)->id())->ignore($cupom?->id)],
            'descricao' => ['nullable', 'string', 'max:150'],
            'evento' => ['nullable', Rule::in($eventos->keys()->all())],
            'tipo_desconto' => ['required', 'in:percentual,valor_fixo'],
            'valor' => ['required', 'numeric', 'gt:0', $request->input('tipo_desconto') === 'percentual' ? 'max:100' : 'max:100000'],
            'valor_minimo_pedido' => ['nullable', 'numeric', 'min:0'],
            'max_usos' => ['nullable', 'integer', 'min:1'],
            'max_usos_cpf' => ['nullable', 'integer', 'min:1'],
            'valido_de' => ['nullable', 'date'],
            'valido_ate' => ['nullable', 'date', 'after:valido_de'],
            'lotes' => ['nullable', 'array'], 'lotes.*' => ['integer'],
            'ativo' => ['nullable', 'boolean'],
        ], [
            'codigo.regex' => 'Use só letras, números, "-" e "_" (sem espaços).', 'codigo.unique' => 'Já existe um cupom com esse código.',
            'valor.max' => 'Percentual de no máximo 100%.', 'valido_ate.after' => 'O fim da validade precisa ser depois do início.',
        ], ['valor' => 'desconto', 'max_usos' => 'limite de usos', 'max_usos_cpf' => 'limite por CPF']);

        if ($cupom && $d['max_usos'] !== null && $d['max_usos'] < $cupom->usos) {
            throw \Illuminate\Validation\ValidationException::withMessages(['max_usos' => "O cupom já foi usado {$cupom->usos} vez(es): o limite não pode ser menor."]);
        }
        $eventoId = ($d['evento'] ?? null) ? $eventos[$d['evento']] : null;
        // Lotes só valem para cupom de um evento e precisam ser desse evento.
        $lotes = $eventoId ? Lote::where('evento_id', $eventoId)->whereIn('id', $d['lotes'] ?? [])->pluck('id')->all() : [];

        return [[
            'codigo' => $d['codigo'], 'descricao' => $d['descricao'] ?? null, 'evento_id' => $eventoId,
            'tipo_desconto' => $d['tipo_desconto'], 'valor' => $d['valor'], 'aplica_em' => 'ingresso',
            'valor_minimo_pedido' => $d['valor_minimo_pedido'] ?? null, 'max_usos' => $d['max_usos'] ?? null, 'max_usos_cpf' => $d['max_usos_cpf'] ?? null,
            'valido_de' => ($d['valido_de'] ?? null) ? utc_de_local($d['valido_de']) : null,
            'valido_ate' => ($d['valido_ate'] ?? null) ? utc_de_local($d['valido_ate']) : null,
            'ativo' => (bool) ($d['ativo'] ?? false),
        ], $lotes];
    }
}
