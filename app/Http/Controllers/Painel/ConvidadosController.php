<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Lote;
use App\Models\Pedido;
use App\Support\Auditoria;
use App\Support\EmissaoCortesia;
use App\Support\Notificacoes;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Divulgação → Convidados: listas por evento (VIP, imprensa...), convidados e emissão de ingressos de cortesia. */
class ConvidadosController extends Controller
{
    public const STATUS = ['pendente' => 'Pendente', 'enviado' => 'Convite enviado', 'confirmado' => 'Confirmado',
        'emitido' => 'Ingresso emitido', 'recusado' => 'Recusou', 'cancelado' => 'Cancelado'];

    public function index(Request $request)
    {
        Gate::authorize('convidado.gerenciar');
        [$eventos, $evento] = ParticipantesController::eventoEscolhido($request);
        $listas = $evento ? DB::table('lista_convidados as l')->join('lote as lt', 'lt.id', '=', 'l.lote_id')
            ->where('l.organizador_id', $this->org())->where('l.evento_id', $evento->id)
            ->select('l.*', 'lt.nome as lote_nome')
            ->selectSub(fn ($q) => $q->from('convidado')->whereColumn('lista_id', 'l.id')->where('status', '<>', 'cancelado')->selectRaw('COUNT(*)'), 'total')
            ->selectSub(fn ($q) => $q->from('convidado')->whereColumn('lista_id', 'l.id')->where('status', 'emitido')->selectRaw('COUNT(*)'), 'emitidos')
            ->orderBy('l.nome')->get() : collect();
        $lotes = $evento ? Lote::with('tipo')->where('evento_id', $evento->id)->orderBy('ordem')->get() : collect();

        return view('painel.convidados.index', compact('eventos', 'evento', 'listas', 'lotes'));
    }

    public function salvarLista(Request $request, Evento $evento, ?int $lista = null)
    {
        Gate::authorize('convidado.gerenciar');
        $this->autorizar($evento);
        $d = $request->validate([
            'nome' => ['required', 'string', 'max:80'],
            'lote' => ['required', Rule::exists('lote', 'id')->where('evento_id', $evento->id)],
            'limite_convidados' => ['nullable', 'integer', 'min:1'],
            'max_acompanhantes' => ['required', 'integer', 'min:0', 'max:20'],
        ], [], ['lote' => 'lote dos ingressos', 'limite_convidados' => 'limite de convidados', 'max_acompanhantes' => 'acompanhantes por convidado']);
        $dados = ['nome' => $d['nome'], 'lote_id' => $d['lote'], 'limite_convidados' => $d['limite_convidados'] ?? null,
            'max_acompanhantes' => $d['max_acompanhantes'], 'responsavel_id' => auth()->id()];
        if ($lista) {
            $this->buscarLista($evento, $lista);
            DB::table('lista_convidados')->where('id', $lista)->update($dados);
        } else {
            $lista = DB::table('lista_convidados')->insertGetId($dados + ['organizador_id' => $this->org(), 'evento_id' => $evento->id]);
        }
        Auditoria::registrar('convidado.lista_salvar', $evento, null, ['lista_id' => $lista] + $dados);

        return redirect()->route('convidados.lista', [$evento, $lista])->with('ok', "Lista “{$d['nome']}” salva.");
    }

    public function lista(Evento $evento, int $lista)
    {
        Gate::authorize('convidado.gerenciar');
        $this->autorizar($evento);
        $l = $this->buscarLista($evento, $lista);
        $convidados = DB::table('convidado as c')->leftJoin('pedido as p', 'p.id', '=', 'c.pedido_id')->where('c.lista_id', $l->id)
            ->select('c.*', 'p.codigo as pedido_codigo', 'p.uuid as pedido_uuid')->orderBy('c.nome')->get();
        $lotes = Lote::with('tipo')->where('evento_id', $evento->id)->orderBy('ordem')->get();
        $lote = $lotes->firstWhere('id', $l->lote_id);

        return view('painel.convidados.lista', ['evento' => $evento, 'l' => $l, 'convidados' => $convidados, 'lotes' => $lotes, 'lote' => $lote]);
    }

    /** Adiciona um convidado ou vários (um por linha: "Nome; e-mail; acompanhantes"). */
    public function adicionar(Request $request, Evento $evento, int $lista)
    {
        Gate::authorize('convidado.gerenciar');
        $this->autorizar($evento);
        $l = $this->buscarLista($evento, $lista);
        $linhas = [];
        if ($request->filled('lote_texto')) {
            foreach (preg_split('/\r?\n/', (string) $request->input('lote_texto')) as $n => $linha) {
                if (trim($linha) === '') {
                    continue;
                }
                $partes = array_map('trim', preg_split('/[;,\t]/', $linha));
                $linhas[] = ['nome' => $partes[0] ?? '', 'email' => $partes[1] ?? null, 'acompanhantes' => (int) ($partes[2] ?? 0), 'linha' => $n + 1];
            }
        } else {
            $d = $request->validate(['nome' => ['required', 'string', 'max:150'], 'email' => ['nullable', 'email:filter', 'max:150'],
                'telefone' => ['nullable', 'string', 'max:20'], 'acompanhantes' => ['nullable', 'integer', 'min:0']]);
            $linhas[] = $d + ['linha' => null];
        }
        $erros = [];
        foreach ($linhas as $x) {
            $rotulo = $x['linha'] ? "Linha {$x['linha']}: " : '';
            if (mb_strlen($x['nome']) < 2) {
                $erros[] = $rotulo.'nome obrigatório.';
            } elseif ($x['email'] && ! filter_var($x['email'], FILTER_VALIDATE_EMAIL)) {
                $erros[] = $rotulo."e-mail inválido ({$x['email']}).";
            } elseif (($x['acompanhantes'] ?? 0) > $l->max_acompanhantes) {
                $erros[] = $rotulo."máximo de {$l->max_acompanhantes} acompanhante(s) nesta lista.";
            }
        }
        $atual = DB::table('convidado')->where('lista_id', $l->id)->where('status', '<>', 'cancelado')->count();
        if ($l->limite_convidados && $atual + count($linhas) > $l->limite_convidados) {
            $erros[] = "A lista aceita até {$l->limite_convidados} convidados (já tem {$atual}).";
        }
        if ($erros) {
            throw ValidationException::withMessages(['convidados' => $erros]);
        }
        foreach ($linhas as $x) {
            DB::table('convidado')->insert([
                'lista_id' => $l->id, 'nome' => mb_substr($x['nome'], 0, 150), 'email' => $x['email'] ? mb_strtolower($x['email']) : null,
                'telefone' => ($x['telefone'] ?? null) ? preg_replace('/\D/', '', $x['telefone']) : null, 'acompanhantes' => (int) ($x['acompanhantes'] ?? 0),
                'token_hash' => hash('sha256', Str::random(40)), 'status' => 'pendente', 'adicionado_por' => auth()->id(),
            ]);
        }
        Auditoria::registrar('convidado.adicionar', $evento, null, ['lista_id' => $l->id, 'quantidade' => count($linhas)]);

        return back()->with('ok', count($linhas).' convidado(s) adicionado(s).');
    }

    /** Emite os ingressos de cortesia (titular + acompanhantes) e envia por e-mail. ?todos=1 emite todos os pendentes com e-mail. */
    public function emitir(Request $request, Evento $evento, int $lista)
    {
        Gate::authorize('convidado.gerenciar');
        Gate::authorize('pedido.emitir_manual');
        $this->autorizar($evento);
        $l = $this->buscarLista($evento, $lista);
        $lote = Lote::where('evento_id', $evento->id)->findOrFail($l->lote_id);
        $q = DB::table('convidado')->where('lista_id', $l->id)->whereIn('status', ['pendente', 'enviado', 'confirmado']);
        $alvos = $request->boolean('todos') ? $q->whereNotNull('email')->get() : $q->where('id', (int) $request->input('convidado'))->get();
        if ($alvos->isEmpty()) {
            return back()->withErrors(['convidados' => 'Nenhum convidado pendente com e-mail para emitir.']);
        }
        $emitidos = 0;
        foreach ($alvos as $c) {
            if (! $c->email) {
                throw ValidationException::withMessages(['convidados' => "Cadastre o e-mail de {$c->nome} para enviar o ingresso."]);
            }
            $nomes = [$c->nome];
            for ($i = 1; $i <= $c->acompanhantes; $i++) {
                $nomes[] = "Acompanhante {$i} de {$c->nome}";
            }
            $pedido = EmissaoCortesia::emitir($evento, $lote, $c->email, $nomes, 'lista_convidados', $c->telefone);
            DB::table('convidado')->where('id', $c->id)->update(['status' => 'emitido', 'pedido_id' => $pedido->id]);
            $pedido->load(['ingressos.participante', 'ingressos.tipo', 'ingressos.lote']);
            Notificacoes::ingressosConfirmados($pedido, $pedido->comprador, $evento->loadMissing('local'), app(Tenant::class)->organizador(), $pedido->ingressos);
            $emitidos++;
        }

        return back()->with('ok', "{$emitidos} convite(s) emitido(s): os ingressos foram enviados por e-mail.");
    }

    /** Remove convidado pendente; se já emitido, cancela os ingressos. */
    public function remover(Request $request, Evento $evento, int $lista, int $convidado)
    {
        Gate::authorize('convidado.gerenciar');
        $this->autorizar($evento);
        $l = $this->buscarLista($evento, $lista);
        $c = DB::table('convidado')->where('lista_id', $l->id)->where('id', $convidado)->first();
        abort_unless($c, 404);
        if ($c->pedido_id) {
            $pedido = Pedido::with('ingressos')->findOrFail($c->pedido_id);
            \App\Support\CancelamentoIngressos::cancelar($pedido, $pedido->ingressos, 'decisao_organizador', 'Convidado removido da lista', false);
        }
        DB::table('convidado')->where('id', $c->id)->update(['status' => 'cancelado']);
        Auditoria::registrar('convidado.remover', $evento, null, ['convidado' => $c->nome, 'lista_id' => $l->id]);

        return back()->with('ok', "{$c->nome} removido(a) da lista".($c->pedido_id ? ' e os ingressos foram cancelados.' : '.'));
    }

    // ---------- apoio ----------

    private function org(): int
    {
        return app(Tenant::class)->id();
    }

    private function buscarLista(Evento $evento, int $id): object
    {
        $l = DB::table('lista_convidados')->where('organizador_id', $this->org())->where('evento_id', $evento->id)->where('id', $id)->first();
        abort_unless($l, 404);

        return $l;
    }

    private function autorizar(Evento $evento): void
    {
        abort_unless(Evento::visiveis()->whereKey($evento->id)->exists(), 404);
    }
}
