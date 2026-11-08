<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Support\Auditoria;
use App\Support\Formularios;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Gestão → Formulários: campos pedidos para cada participante na compra do ingresso (V015). */
class FormulariosController extends Controller
{
    private function org(): int
    {
        abort_unless(Formularios::ativo(), 404);

        return app(Tenant::class)->id();
    }

    public function index()
    {
        Gate::authorize('evento.ver');
        $org = $this->org();
        Formularios::padrao($org); // garante o padrão
        $formularios = DB::table('formulario as f')->where('f.organizador_id', $org)->whereNull('f.evento_id')
            ->orderByDesc('f.padrao')->orderBy('f.nome')
            ->select('f.*', DB::raw('(SELECT COUNT(*) FROM formulario_campo fc WHERE fc.formulario_id = f.id AND fc.ativo = 1) AS campos'),
                DB::raw('(SELECT COUNT(*) FROM evento e WHERE e.formulario_id = f.id) AS eventos'))->get();

        return view('painel.formularios.index', compact('formularios'));
    }

    public function editar(?int $id = null)
    {
        Gate::authorize('evento.criar');
        $org = $this->org();
        $f = $id ? (DB::table('formulario')->where('organizador_id', $org)->whereNull('evento_id')->where('id', $id)->first() ?? abort(404)) : null;
        $catalogo = DB::table('campo_catalogo')->orderBy('ordem')->get();
        $atuais = $f ? DB::table('formulario_campo')->where('formulario_id', $f->id)->where('ativo', 1)->orderBy('ordem')->get() : collect();

        return view('painel.formularios.editar', ['f' => $f, 'catalogo' => $catalogo,
            'doCatalogo' => $atuais->whereNotNull('campo_catalogo_id')->keyBy('campo_catalogo_id'),
            'personalizados' => $atuais->whereNull('campo_catalogo_id')->values()]);
    }

    public function salvar(Request $request, ?int $id = null)
    {
        Gate::authorize('evento.criar');
        $org = $this->org();
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:80'],
            'cat' => ['array'], 'cat.*.pedir' => ['nullable'], 'cat.*.obrigatorio' => ['nullable'], 'cat.*.rotulo' => ['nullable', 'string', 'max:120'],
            'pers' => ['array'], 'pers.*.id' => ['nullable', 'integer'], 'pers.*.rotulo' => ['nullable', 'string', 'max:120'],
            'pers.*.tipo' => ['nullable', 'in:'.implode(',', array_keys(Formularios::TIPOS_PERSONALIZADOS))],
            'pers.*.opcoes' => ['nullable', 'string', 'max:1000'], 'pers.*.obrigatorio' => ['nullable'],
        ], [], ['nome' => 'nome do formulário']);
        $catalogo = DB::table('campo_catalogo')->orderBy('ordem')->get()->keyBy('id');
        foreach ((array) ($d['pers'] ?? []) as $p) {
            if (filled($p['rotulo'] ?? null) && ($p['tipo'] ?? '') === 'selecao' && blank($p['opcoes'] ?? null)) {
                throw ValidationException::withMessages(['pers' => "Informe as opções da lista “{$p['rotulo']}” (separadas por ponto e vírgula)."]);
            }
        }

        $id = DB::transaction(function () use ($id, $org, $d, $catalogo) {
            if ($id) {
                DB::table('formulario')->where('organizador_id', $org)->whereNull('evento_id')->where('id', $id)->update(['nome' => $d['nome']]) ;
                abort_unless(DB::table('formulario')->where('organizador_id', $org)->where('id', $id)->exists(), 404);
            } else {
                $id = DB::table('formulario')->insertGetId(['organizador_id' => $org, 'nome' => $d['nome'], 'padrao' => 0]);
            }
            $ordem = 0;
            $mantidos = [];
            // Nome completo é sempre pedido e obrigatório.
            foreach ($catalogo as $cid => $c) {
                $linha = $d['cat'][$cid] ?? [];
                $pedir = $c->codigo === 'nome' || ! empty($linha['pedir']);
                if (! $pedir) {
                    continue;
                }
                $dados = ['rotulo' => filled($linha['rotulo'] ?? null) ? $linha['rotulo'] : $c->rotulo_padrao,
                    'obrigatorio' => $c->codigo === 'nome' || ! empty($linha['obrigatorio']), 'ordem' => ++$ordem, 'ativo' => 1];
                $existe = DB::table('formulario_campo')->where('formulario_id', $id)->where('campo_catalogo_id', $cid)->whereNull('ingresso_tipo_id')->first();
                if ($existe) {
                    DB::table('formulario_campo')->where('id', $existe->id)->update($dados);
                    $mantidos[] = $existe->id;
                } else {
                    $mantidos[] = DB::table('formulario_campo')->insertGetId($dados + ['formulario_id' => $id, 'campo_catalogo_id' => $cid]);
                }
            }
            foreach ((array) ($d['pers'] ?? []) as $p) {
                if (blank($p['rotulo'] ?? null)) {
                    continue;
                }
                $tipo = $p['tipo'] ?? 'texto';
                $opcoes = $tipo === 'selecao' ? json_encode(array_values(array_filter(array_map('trim', explode(';', (string) $p['opcoes'])))), JSON_UNESCAPED_UNICODE) : null;
                $dados = ['rotulo' => $p['rotulo'], 'tipo_personalizado' => $tipo, 'opcoes' => $opcoes, 'obrigatorio' => ! empty($p['obrigatorio']), 'ordem' => ++$ordem, 'ativo' => 1];
                $existe = ! empty($p['id']) ? DB::table('formulario_campo')->where('formulario_id', $id)->whereNull('campo_catalogo_id')->find($p['id']) : null;
                if ($existe) {
                    DB::table('formulario_campo')->where('id', $existe->id)->update($dados);
                    $mantidos[] = $existe->id;
                } else {
                    $mantidos[] = DB::table('formulario_campo')->insertGetId($dados + ['formulario_id' => $id,
                        'codigo_personalizado' => 'p'.substr(md5(uniqid('', true)), 0, 10)]);
                }
            }
            // Campos tirados ficam inativos (respostas antigas continuam ligadas a eles).
            DB::table('formulario_campo')->where('formulario_id', $id)->whereNotIn('id', $mantidos)->update(['ativo' => 0]);

            return $id;
        });
        Auditoria::registrar('formulario.salvar', \App\Models\Organizador::findOrFail($org), null, ['formulario_id' => $id, 'nome' => $d['nome']]);

        return redirect()->route('formularios.index')->with('ok', "Formulário “{$d['nome']}” salvo.");
    }

    public function padrao(int $id)
    {
        Gate::authorize('evento.criar');
        $org = $this->org();
        abort_unless(DB::table('formulario')->where('organizador_id', $org)->whereNull('evento_id')->where('id', $id)->exists(), 404);
        DB::table('formulario')->where('organizador_id', $org)->update(['padrao' => DB::raw("id = {$id}")]);
        Auditoria::registrar('formulario.padrao', \App\Models\Organizador::findOrFail($org), null, ['formulario_id' => $id]);

        return back()->with('ok', 'Formulário padrão alterado. Vale para os eventos que não escolheram outro.');
    }

    public function excluir(int $id)
    {
        Gate::authorize('evento.criar');
        $org = $this->org();
        $f = DB::table('formulario')->where('organizador_id', $org)->whereNull('evento_id')->where('id', $id)->first() ?? abort(404);
        if ($f->padrao) {
            return back()->withErrors(['formulario' => 'O formulário padrão não pode ser excluído. Defina outro como padrão antes.']);
        }
        if (DB::table('evento')->where('formulario_id', $id)->exists()) {
            return back()->withErrors(['formulario' => 'Há eventos usando este formulário. Troque o formulário desses eventos antes de excluir.']);
        }
        $usado = DB::table('participante_resposta as r')->join('formulario_campo as fc', 'fc.id', '=', 'r.formulario_campo_id')->where('fc.formulario_id', $id)->exists();
        if ($usado) {
            return back()->withErrors(['formulario' => 'Este formulário já tem respostas de participantes e não pode ser excluído.']);
        }
        DB::table('formulario_campo')->where('formulario_id', $id)->delete();
        DB::table('formulario')->where('id', $id)->delete();
        Auditoria::registrar('formulario.excluir', \App\Models\Organizador::findOrFail($org), null, ['formulario_id' => $id, 'nome' => $f->nome]);

        return back()->with('ok', "Formulário “{$f->nome}” excluído.");
    }
}
