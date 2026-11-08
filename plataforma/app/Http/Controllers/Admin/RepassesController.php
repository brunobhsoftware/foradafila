<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizador;
use App\Support\Auditoria;
use App\Support\Recebimento;
use App\Support\Repasses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Administração → Repasses: quanto a BH Software deve a cada produtora e o registro de cada transferência. */
class RepassesController extends Controller
{
    public function index()
    {
        abort_unless(\App\Support\Esquema::temV012(), 404);
        $pendentes = Repasses::pendentes()->groupBy('organizador_id');
        $orgs = Organizador::withoutGlobalScopes()->whereIn('id', $pendentes->keys())->get()->keyBy('id');
        $linhas = $pendentes->map(function ($pgs, $orgId) use ($orgs) {
            $validos = $pgs->reject(fn ($r) => $r->devolver);

            return (object) [
                'org' => $orgs[$orgId], 'destino' => Recebimento::destino($orgs[$orgId]),
                'liberado' => round($validos->where('pode_repassar', true)->sum('valor_liquido_org'), 2),
                'aguardando' => round($validos->where('pode_repassar', false)->sum('valor_liquido_org'), 2),
                'comissao' => round($validos->sum('comissao_retida'), 2),
                'proxima' => $validos->where('pode_repassar', false)->min('liberado_em'),
                'devolver' => $pgs->where('devolver', true)->count(),
            ];
        })->sortByDesc('liberado')->values();
        $feitos = DB::table('repasse as r')->join('organizador as o', 'o.id', '=', 'r.organizador_id')
            ->orderByDesc('r.id')->limit(30)->get(['r.*', 'o.nome_fantasia', 'o.razao_social']);

        return view('admin.repasses', compact('linhas', 'feitos'));
    }

    /** Registra que o repasse foi feito (Pix/TED já enviado pelo banco): fecha os pagamentos liberados da produtora. */
    public function registrar(Request $request, string $organizador)
    {
        $org = Organizador::withoutGlobalScopes()->where('uuid', $organizador)->firstOrFail();
        $d = $request->validate(['comprovante' => ['required', 'string', 'max:255'], 'pago_em' => ['required', 'date'],
            'observacao' => ['nullable', 'string', 'max:500']],
            ['comprovante.required' => 'Informe o identificador da transferência (ex.: E2E do Pix ou nº do TED).']);
        $itens = Repasses::pendentes($org->id)->where('pode_repassar', true)->reject(fn ($r) => $r->devolver);
        if ($itens->isEmpty()) {
            return back()->withErrors(['comprovante' => 'Não há valores liberados para repassar a '.$org->nomeExibicao().'.']);
        }
        $id = DB::transaction(function () use ($org, $d, $itens, $request) {
            $id = DB::table('repasse')->insertGetId([
                'uuid' => (string) Str::uuid(), 'organizador_id' => $org->id,
                'valor_vendas' => round($itens->sum('valor'), 2), 'valor_comissao' => round($itens->sum('comissao_retida'), 2),
                'valor_repasse' => round($itens->sum('valor_liquido_org'), 2), 'status' => 'pago',
                'destino' => mb_substr((string) Recebimento::destino($org), 0, 255), 'pago_em' => utc_de_local($d['pago_em'].'T12:00'),
                'comprovante' => $d['comprovante'], 'observacao' => $d['observacao'] ?? null, 'criado_por' => $request->user()->id,
            ]);
            DB::table('pagamento')->whereIn('id', $itens->pluck('id'))->whereNull('repasse_id')->update(['repasse_id' => $id]);

            return $id;
        });
        Auditoria::registrar('admin.repasse_registrado', $org, null, ['repasse_id' => $id, 'valor' => round($itens->sum('valor_liquido_org'), 2), 'pagamentos' => $itens->count()]);

        return back()->with('ok', 'Repasse de '.moeda($itens->sum('valor_liquido_org')).' para '.$org->nomeExibicao().' registrado.');
    }
}
