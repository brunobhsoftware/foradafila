<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
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
        $aba = in_array($request->query('aba'), ['vendas', 'checkin', 'ferramentas'], true) ? $request->query('aba') : 'checkin';
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

    private function vendas(Evento $evento): array
    {
        $pagos = DB::table('pedido')->where('evento_id', $evento->id)->whereIn('status', ['pago', 'reembolso_parcial']);
        $porLote = DB::table('ingresso as i')->join('lote as l', 'l.id', '=', 'i.lote_id')->join('ingresso_tipo as t', 't.id', '=', 'l.ingresso_tipo_id')
            ->where('i.evento_id', $evento->id)->whereIn('i.status', ['valido', 'utilizado'])
            ->groupBy('l.id', 'l.nome', 't.nome')->orderByDesc(DB::raw('COUNT(*)'))
            ->get(['t.nome as tipo', 'l.nome as lote', DB::raw('COUNT(*) as qtd'), DB::raw('SUM(i.valor_pago) as valor')]);

        return [
            'total' => (float) (clone $pagos)->sum('valor_total'),
            'pedidos' => (clone $pagos)->count(),
            'ingressos' => (int) $porLote->sum('qtd'),
            'hoje' => (float) (clone $pagos)->where('pago_em', '>=', now()->setTimezone(JanelaCheckin::FUSO)->startOfDay()->utc())->sum('valor_total'),
            'por_lote' => $porLote,
        ];
    }

    private function acesso(Evento $evento): void
    {
        abort_unless(Gate::any(self::PERMISSOES), 403);
        abort_unless(Evento::visiveis()->whereKey($evento->id)->exists(), 404);
    }
}
