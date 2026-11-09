<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Publico\EventoPublicoController;
use App\Http\Controllers\Painel\CheckinController;
use App\Models\Evento;
use App\Models\Ingresso;
use App\Support\JanelaCheckin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * App de check-in da equipe (/app, decisão do Bruno 09/11/2026): pensado para o celular e instalável (PWA; os apps Android/iOS
 * abrem esta mesma página). Eventos → abas Vendas / Check-in / Ferramentas conforme o nível de acesso; leitor de QR pela câmera
 * (jsQR, funciona também no iPhone) que registra a entrada pelo mesmo CheckinController::registrar do painel. Check-in só no dia
 * do evento, da abertura dos portões ao término (App\Support\JanelaCheckin).
 */
class CheckinAppController extends Controller
{
    private const PERMISSOES = ['checkin.realizar', 'checkin.acompanhar', 'participante.ver', 'pedido.ver'];

    public function index()
    {
        abort_unless(Gate::any(self::PERMISSOES), 403, 'Seu nível de acesso não inclui o check-in. Fale com o produtor.');
        $eventos = Evento::visiveis()->whereIn('status', ['publicado', 'encerrado'])->with('local')
            ->where('data_fim', '>=', now()->subDays(30))->orderBy('data_inicio')->get();
        $hoje = $eventos->filter(fn ($e) => JanelaCheckin::situacao($e)['aberta']);
        $proximos = $eventos->filter(fn ($e) => $e->data_inicio->isFuture())->diff($hoje);
        $passados = $eventos->diff($hoje)->diff($proximos)->sortByDesc('data_inicio');

        return view('app.index', compact('hoje', 'proximos', 'passados'));
    }

    public function evento(Request $request, Evento $evento)
    {
        $this->acesso($evento);
        // abre em Vendas para quem vê vendas (gerente/produtor); só quem é de check-in abre no Check-in (decisão do Bruno, 09/11/2026)
        $aba = in_array($request->query('aba'), ['vendas', 'checkin', 'ferramentas'], true) ? $request->query('aba') : (Gate::allows('pedido.ver') ? 'vendas' : 'checkin');
        if ($aba === 'vendas' && ! Gate::allows('pedido.ver')) {
            $aba = 'checkin';
        }
        $janela = JanelaCheckin::situacao($evento);
        $numeros = CheckinController::numeros($evento);
        $vendas = Gate::allows('pedido.ver') ? $this->vendas($evento) : null;

        return view('app.evento', compact('evento', 'aba', 'janela', 'numeros', 'vendas'));
    }

    /** Leitor de QR (tela cheia). */
    public function ler(Evento $evento)
    {
        $this->acesso($evento);
        Gate::authorize('checkin.realizar');

        return view('app.ler', ['evento' => $evento, 'janela' => JanelaCheckin::situacao($evento), 'numeros' => CheckinController::numeros($evento)]);
    }

    public function participantes(Request $request, Evento $evento)
    {
        $this->acesso($evento);
        abort_unless(Gate::any(['participante.ver', 'checkin.realizar']), 403);
        $b = trim((string) $request->query('q', ''));
        $filtro = in_array($request->query('f'), ['entraram', 'faltam'], true) ? $request->query('f') : 'todos';
        $digitos = preg_replace('/\D/', '', $b);
        $lista = Ingresso::with(['participante', 'tipo', 'lote'])->where('evento_id', $evento->id)->whereIn('status', ['valido', 'utilizado'])
            ->when($filtro === 'entraram', fn ($q) => $q->where('status', 'utilizado'))
            ->when($filtro === 'faltam', fn ($q) => $q->where('status', 'valido'))
            ->when($b !== '', fn ($q) => $q->where(fn ($w) => $w->whereHas('participante', fn ($p) => $p->where('nome', 'like', "%{$b}%")
                ->when(strlen($digitos) >= 4, fn ($x) => $x->orWhere('cpf', 'like', "%{$digitos}%")))
                ->when($digitos !== '' && strlen($digitos) <= 6, fn ($x) => $x->orWhere('numero', (int) $digitos))))
            ->orderBy('numero')->paginate(50)->withQueryString();

        return view('app.participantes', ['evento' => $evento, 'lista' => $lista, 'b' => $b, 'filtro' => $filtro,
            'janela' => JanelaCheckin::situacao($evento), 'numeros' => CheckinController::numeros($evento)]);
    }

    /** Números atualizados (o app recarrega a cada 15 s). */
    public function numeros(Evento $evento)
    {
        $this->acesso($evento);

        return response()->json(CheckinController::numeros($evento) + ['janela' => JanelaCheckin::situacao($evento)['aberta']]);
    }

    /** Painel de vendas do app (decisão do Bruno, 09/11/2026): totais, hoje, taxa, devoluções, líquido, por ingresso (gráfico) e lotes à venda. */
    private function vendas(Evento $evento): array
    {
        $inicioHoje = now()->setTimezone(JanelaCheckin::FUSO)->startOfDay()->utc();
        $pedidos = DB::table('pedido')->where('evento_id', $evento->id)->whereIn('status', ['pago', 'reembolso_parcial', 'reembolsado']);
        $validos = DB::table('ingresso as i')->leftJoin('pedido_item as pi', 'pi.id', '=', 'i.pedido_item_id')
            ->where('i.evento_id', $evento->id)->whereIn('i.status', ['valido', 'utilizado']);
        $porLote = (clone $validos)->join('lote as l', 'l.id', '=', 'i.lote_id')->join('ingresso_tipo as t', 't.id', '=', 'l.ingresso_tipo_id')
            ->groupBy('l.id', 'l.nome', 't.nome')->orderByDesc(DB::raw('COUNT(*)'))
            ->get(['t.nome as tipo', 'l.nome as lote', DB::raw('COUNT(*) as qtd'), DB::raw('SUM(i.valor_pago) as valor')]);
        $total = (float) (clone $pedidos)->sum('valor_total');
        $taxa = (float) (clone $validos)->sum('pi.taxa_unitaria');
        $devolvido = (float) DB::table('reembolso as r')->join('pedido as p', 'p.id', '=', 'r.pedido_id')
            ->where('p.evento_id', $evento->id)->whereIn('r.status', ['aprovado', 'concluido'])->sum('r.valor');
        $ingressos = (int) $porLote->sum('qtd');
        $qtdPedidos = (clone $pedidos)->count();
        $lotes = $evento->lotes()->with('tipo')->orderBy('id')->get()->map(fn ($l) => (object) [
            'nome' => ($l->tipo?->nome ? $l->tipo->nome.' — ' : '').$l->nome, 'preco' => (float) $l->preco,
            'vendidos' => (int) $l->vendido, 'quantidade' => (int) $l->quantidade,
            'situacao' => $l->vendido >= $l->quantidade ? 'Esgotado'
                : (EventoPublicoController::loteAVenda($evento, $l) ? 'À venda' : ($l->status === 'pausado' ? 'Desativado' : ($l->venda_fim && $l->venda_fim < now() ? 'Encerrado' : 'Ainda não abriu'))),
        ]);

        return [
            'total' => $total,
            'pedidos' => $qtdPedidos,
            'ingressos' => $ingressos,
            'ofertados' => (int) $evento->lotes()->sum('quantidade'),
            'cortesias' => (int) (clone $validos)->where('i.valor_pago', 0)->count(),
            'hoje' => (float) (clone $pedidos)->where('pago_em', '>=', $inicioHoje)->sum('valor_total'),
            'ingressos_hoje' => (int) (clone $validos)->where('i.criado_em', '>=', $inicioHoje)->count(),
            'taxa' => $taxa,
            'devolvido' => $devolvido,
            'liquido' => round($total - $taxa - $devolvido, 2),
            'ticket' => $qtdPedidos ? $total / $qtdPedidos : 0,
            'por_lote' => $porLote,
            'lotes' => $lotes,
        ];
    }

    private function acesso(Evento $evento): void
    {
        abort_unless(Gate::any(self::PERMISSOES), 403);
        abort_unless(Evento::visiveis()->whereKey($evento->id)->exists(), 404);
    }
}
