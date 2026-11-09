<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizador;
use App\Support\Auditoria;
use App\Support\Impersonacao;
use App\Support\MotorTaxas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Administração: gestão das empresas (organizadores), status, "assumir conta" e taxas específicas. */
class EmpresasController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    /** Sem o V008 (tabelas de taxas e de impersonação), mostra o aviso em vez de erro. */
    public static function middleware(): array
    {
        return [new \Illuminate\Routing\Controllers\Middleware(
            fn ($request, $next) => \App\Support\Esquema::temV008() ? $next($request) : response()->view('admin.precisa-v008'), except: ['sairDaConta'])];
    }

    public const SITUACOES = ['pendente' => 'Em teste', 'ativo' => 'Ativo', 'suspenso' => 'Suspenso', 'bloqueado_debito' => 'Bloqueado (débito)', 'encerrado' => 'Encerrado'];

    public function index(Request $request)
    {
        $f = $request->validate([
            'busca' => ['nullable', 'string', 'max:100'], 'situacao' => ['nullable', 'in:'.implode(',', array_keys(self::SITUACOES))],
            'de' => ['nullable', 'date'], 'ate' => ['nullable', 'date'],
        ]);
        $empresas = Organizador::query()
            ->when($f['busca'] ?? null, function ($q, $b) {
                $digitos = preg_replace('/\D/', '', $b);
                $q->where(fn ($w) => $w->where('razao_social', 'like', "%{$b}%")->orWhere('nome_fantasia', 'like', "%{$b}%")
                    ->when($digitos !== '', fn ($x) => $x->orWhere('documento', 'like', "%{$digitos}%")));
            })
            ->when($f['situacao'] ?? null, fn ($q, $s) => $q->where('situacao', $s))
            ->when($f['de'] ?? null, fn ($q, $d) => $q->where('criado_em', '>=', $d.' 00:00:00'))
            ->when($f['ate'] ?? null, fn ($q, $d) => $q->where('criado_em', '<=', $d.' 23:59:59'))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $planos = DB::table('organizador_plano as op')->join('plano as p', 'p.id', '=', 'op.plano_id')
            ->whereIn('op.organizador_id', $empresas->pluck('id'))->whereNull('op.fim')->pluck('p.nome', 'op.organizador_id');
        $especificas = DB::table('taxa_organizador')->whereIn('organizador_id', $empresas->pluck('id'))
            ->where(fn ($q) => $q->whereNull('fim')->orWhere('fim', '>=', now()->toDateString()))
            ->groupBy('organizador_id')->selectRaw('organizador_id, COUNT(*) n')->pluck('n', 'organizador_id');

        return view('admin.empresas.index', compact('empresas', 'planos', 'especificas', 'f'));
    }

    public function show(Organizador $organizador)
    {
        $id = $organizador->id;
        $numeros = (object) [
            'eventos' => DB::table('evento')->where('organizador_id', $id)->count(),
            'publicados' => DB::table('evento')->where('organizador_id', $id)->where('status', 'publicado')->count(),
            'ingressos' => (int) DB::table('lote')->where('organizador_id', $id)->sum('vendido'),
            'receita' => (float) DB::table('pedido')->where('organizador_id', $id)->whereIn('status', ['pago', 'reembolso_parcial'])->sum('valor_total'),
            'usuarios' => DB::table('organizador_usuario')->where('organizador_id', $id)->where('status', 'ativo')->count(),
        ];
        $usuarios = DB::table('organizador_usuario as ou')->join('usuario as u', 'u.id', '=', 'ou.usuario_id')
            ->join('perfil_acesso as p', 'p.id', '=', 'ou.perfil_id')->where('ou.organizador_id', $id)
            ->select('u.uuid', 'u.nome', 'u.email', 'u.ativo', 'ou.status', 'p.codigo', 'p.nome as perfil', 'u.ultimo_login_em')->orderBy('u.nome')->get();
        $contas = DB::table('conta_pagamento as c')->join('gateway as g', 'g.id', '=', 'c.gateway_id')->where('c.organizador_id', $id)
            ->select('g.nome as gateway', 'c.status', 'c.ambiente', 'c.email_conta', 'c.conectado_em')->get();
        $auditoria = DB::table('auditoria as a')->leftJoin('usuario as u', 'u.id', '=', 'a.usuario_id')
            ->leftJoin('usuario as adm', 'adm.id', '=', 'a.admin_impersonando_id')->where('a.organizador_id', $id)
            ->select('a.acao', 'a.tabela', 'a.registro_id', 'a.ocorrido_em', 'u.nome as usuario', 'adm.nome as admin')->orderByDesc('a.id')->limit(30)->get();
        $impersonacoes = DB::table('impersonacao_log as i')->join('usuario as u', 'u.id', '=', 'i.admin_id')->where('i.organizador_id', $id)
            ->select('i.*', 'u.nome as admin')->orderByDesc('i.id')->limit(10)->get();
        $taxas = DB::table('taxa_organizador as t')->leftJoin('usuario as u', 'u.id', '=', 't.criado_por')->where('t.organizador_id', $id)
            ->select('t.*', 'u.nome as autor')->orderByDesc('t.inicio')->get();
        $simulacao = MotorTaxas::calcular($organizador, 'ingresso_pago', 100);
        $marketing = \App\Support\Marketing::ativo($id);
        $marketingLimite = \App\Support\Marketing::limiteDia($id);

        return view('admin.empresas.show', compact('organizador', 'numeros', 'usuarios', 'contas', 'auditoria', 'impersonacoes', 'taxas', 'simulacao', 'marketing', 'marketingLimite'));
    }

    public function situacao(Request $request, Organizador $organizador)
    {
        $d = $request->validate(['situacao' => ['required', 'in:ativo,suspenso,pendente,encerrado'], 'motivo' => ['nullable', 'string', 'max:255']]);
        $antes = $organizador->getAttributes();
        $organizador->forceFill(['situacao' => $d['situacao'], 'situacao_motivo' => $d['motivo'] ?? null])->save();
        Auditoria::registrar('plataforma.empresa_situacao', $organizador, $antes);

        return back()->with('ok', "Situação de {$organizador->nomeExibicao()} alterada para ".self::SITUACOES[$d['situacao']].'.');
    }

    /** "Assumir conta": entra no painel da empresa como o Produtor, com faixa de aviso e tudo registrado. */
    public function assumir(Request $request, Organizador $organizador)
    {
        $motivo = $request->validate(['motivo' => ['nullable', 'string', 'max:255']])['motivo'] ?? null;
        Impersonacao::iniciar($request->user(), $organizador, $motivo);
        Auditoria::registrar('plataforma.assumir_conta', $organizador, null, ['motivo' => $motivo]);

        return redirect()->route('painel')->with('ok', "Você está navegando como {$organizador->nomeExibicao()}.");
    }

    public function sairDaConta()
    {
        $dados = Impersonacao::encerrar();

        return $dados && ($org = Organizador::find($dados['organizador_id'])) ? redirect()->route('admin.empresas.show', $org)->with('ok', 'Você saiu da conta da empresa e voltou para a Administração.')
            : redirect()->route('admin.inicio');
    }

    /** E-mail marketing por empresa (decisão do Bruno, 09/11/2026): liga/desliga o envio de convites e define o limite diário. */
    public function marketing(Request $request, Organizador $organizador)
    {
        $d = $request->validate([
            'marketing_ativo' => ['nullable', 'boolean'],
            'marketing_limite' => ['required', 'integer', 'min:1', 'max:'.\App\Support\Marketing::LIMITE_MAXIMO],
        ], [], ['marketing_limite' => 'limite diário']);
        $ativo = $request->boolean('marketing_ativo');
        \App\Support\Marketing::definir($organizador->id, $ativo, (int) $d['marketing_limite']);
        Auditoria::registrar('plataforma.marketing_empresa', $organizador, null, ['ativo' => $ativo, 'limite' => (int) $d['marketing_limite']]);

        return redirect()->to(route('admin.empresas.show', $organizador).'#marketing')
            ->with('ok', $ativo ? 'E-mail marketing liberado ('.$d['marketing_limite'].' por dia).' : 'E-mail marketing desligado para esta empresa.');
    }

    // ---------- Taxas específicas da empresa ----------

    public function salvarTaxa(Request $request, Organizador $organizador, ?int $taxa = null)
    {
        $d = $request->validate([
            'operacao' => ['required', 'in:todas,'.implode(',', array_keys(MotorTaxas::OPERACOES))],
            'isento' => ['nullable', 'boolean'],
            'percentual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valor_fixo' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'valor_minimo' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'inicio' => ['required', 'date'], 'fim' => ['nullable', 'date', 'after_or_equal:inicio'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ], ['fim.after_or_equal' => 'A data de fim precisa ser igual ou depois do início.'], ['valor_fixo' => 'tarifa fixa', 'valor_minimo' => 'taxa mínima']);
        $isento = (bool) ($d['isento'] ?? false);
        if (! $isento && $d['percentual'] === null && $d['valor_fixo'] === null && $d['valor_minimo'] === null) {
            return back()->withInput()->withErrors(['percentual' => 'Marque a isenção ou informe pelo menos um valor (percentual, tarifa fixa ou mínima).']);
        }
        $valores = ['operacao' => $d['operacao'], 'isento' => $isento,
            'percentual' => $isento ? null : $d['percentual'], 'valor_fixo' => $isento ? null : $d['valor_fixo'], 'valor_minimo' => $isento ? null : $d['valor_minimo'],
            'inicio' => $d['inicio'], 'fim' => $d['fim'] ?? null, 'observacao' => $d['observacao'] ?? null];
        if ($taxa) {
            // Editar a taxa existente (sem criar outra): vale do início informado até o fim (em branco = sempre ativa).
            $antes = DB::table('taxa_organizador')->where('organizador_id', $organizador->id)->where('id', $taxa)->first();
            abort_unless($antes, 404);
            DB::table('taxa_organizador')->where('id', $taxa)->update($valores);
            Auditoria::registrar('plataforma.taxa_empresa_editar', $organizador, (array) $antes, ['taxa_organizador_id' => $taxa] + $valores);

            return redirect()->to(route('admin.empresas.show', $organizador).'#taxas')->with('ok', 'Taxa da empresa atualizada.');
        }
        $id = DB::table('taxa_organizador')->insertGetId($valores + ['organizador_id' => $organizador->id, 'criado_por' => $request->user()->id]);
        Auditoria::registrar('plataforma.taxa_empresa_criar', $organizador, null, ['taxa_organizador_id' => $id] + $d);

        return redirect()->to(route('admin.empresas.show', $organizador).'#taxas')->with('ok', $isento ? 'Isenção registrada.' : 'Taxa específica registrada.');
    }

    public function encerrarTaxa(Request $request, Organizador $organizador, int $taxa)
    {
        $linha = DB::table('taxa_organizador')->where('organizador_id', $organizador->id)->where('id', $taxa)->first();
        abort_unless($linha, 404);
        // Termina ontem (o histórico fica). Se começou hoje ou depois, nunca valeu: sai da lista (fica na auditoria).
        $ontem = now()->subDay()->toDateString();
        if ($linha->inicio > $ontem) {
            DB::table('taxa_organizador')->where('id', $taxa)->delete();
            Auditoria::registrar('plataforma.taxa_empresa_encerrar', $organizador, null, ['taxa_organizador_id' => $taxa, 'removida' => (array) $linha]);
        } else {
            DB::table('taxa_organizador')->where('id', $taxa)->update(['fim' => $ontem]);
            Auditoria::registrar('plataforma.taxa_empresa_encerrar', $organizador, null, ['taxa_organizador_id' => $taxa, 'fim' => $ontem]);
        }

        return redirect()->to(route('admin.empresas.show', $organizador).'#taxas')->with('ok', 'Taxa específica encerrada.');
    }

    /** Administração: manda o link de nova senha para um usuário (ex.: produtor que não recebeu pelo "Esqueci a senha"). */
    public function enviarSenha(\Illuminate\Http\Request $request, string $usuario)
    {
        $conta = \App\Models\Usuario::where('uuid', $usuario)->firstOrFail();
        \App\Http\Controllers\Auth\SenhaController::enviarLink($conta, 'produtor');
        \App\Support\Auditoria::registrar('admin.senha_link_enviado', $conta, null, ['email' => $conta->email]);

        return back()->with('ok', 'Link para criar nova senha enviado para '.$conta->email.'. Confira em E-mails enviados.');
    }

    /** Administração: corrige o e-mail de login (ex.: digitado com acento, "estaçãoslod@gmail.com"). */
    public function corrigirEmail(\Illuminate\Http\Request $request, string $usuario)
    {
        $conta = \App\Models\Usuario::where('uuid', $usuario)->firstOrFail();
        $novo = mb_strtolower(trim((string) $request->input('email')));
        $request->merge(['email' => $novo]);
        $request->validate(['email' => ['required', 'email:filter', 'max:150', \Illuminate\Validation\Rule::unique('usuario', 'email')->ignore($conta->id)]],
            ['email.email' => 'E-mail inválido (sem acentos, cedilha ou espaços).', 'email.unique' => 'Já existe outro login com esse e-mail.']);
        $anterior = $conta->email;
        $conta->forceFill(['email' => $novo, 'email_verificado_em' => null])->save();
        \App\Support\Auditoria::registrar('admin.email_login_corrigido', $conta, ['email' => $anterior], ['email' => $novo]);

        return back()->with('ok', "E-mail de login trocado de {$anterior} para {$novo}. Agora envie o link de nova senha.");
    }
}
