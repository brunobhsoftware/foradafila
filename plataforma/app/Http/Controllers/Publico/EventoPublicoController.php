<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Organizador;
use App\Support\Tenant;

/**
 * Páginas públicas de evento. Fora da área do produtor não há organizador na
 * sessão, então cada evento é buscado sem o filtro e o Tenant passa a ser o
 * organizador do evento (somente leitura, sem permissões).
 */
class EventoPublicoController extends Controller
{
    /** Status em que a página do evento fica no ar. */
    public const STATUS_PUBLICOS = ['publicado', 'pausado', 'encerrado', 'cancelado'];

    public function vitrine()
    {
        $eventos = Evento::withoutGlobalScope('organizador')->with(['local' => fn ($q) => $q->withoutGlobalScope('organizador')])
            ->where('status', 'publicado')->where('visibilidade', 'publico')
            ->where('data_fim', '>=', now())
            ->orderBy('data_inicio')->paginate(24);

        self::precosMinimos($eventos->getCollection());

        return view('publico.vitrine', compact('eventos'));
    }

    public function show(string $slug, Tenant $tenant)
    {
        self::guardarCupom();
        $evento = self::buscar($slug);
        $tenant->definir($evento->organizador);
        \App\Support\Checkout::liberarVencidas($evento->id); // reservas de pagamento vencidas voltam ao estoque
        $evento->load(['local', 'categoria', 'tipos' => fn ($q) => $q->where('ativo', 1), 'tipos.lotes']);

        // Só os lotes públicos aparecem; ocultos e de convidados dependem de link próprio (fase futura).
        $lotes = $evento->tipos->flatMap->lotes->where('visibilidade', 'publico')->values();
        $aVenda = $lotes->filter(fn ($l) => self::loteAVenda($evento, $l));
        // A página mostra só os ingressos à venda agora, pela regra de cada lote (decisão do Bruno, 08/11/2026):
        // "Por data" → dentro do período; "Por lote" → depois que o anterior terminou. Sem lote à venda, avisa a próxima abertura.
        $proximaAbertura = $lotes->reject(fn ($l) => $aVenda->contains('id', $l->id))
            ->filter(fn ($l) => ! $l->lote_anterior_id && in_array($l->status, ['ativo', 'agendado'], true) && $l->venda_inicio?->isFuture())
            ->min('venda_inicio');
        $lotes = $aVenda->values();

        $vendePago = (bool) \App\Support\Checkout::contaDoEvento($evento);

        return view('publico.evento', compact('evento', 'lotes', 'aVenda', 'vendePago', 'proximaAbertura'));
    }

    public function organizador(string $slug)
    {
        self::guardarCupom();
        $org = Organizador::where('slug', $slug)->where('situacao', '<>', 'encerrado')->firstOrFail();
        $comLocal = ['local' => fn ($q) => $q->withoutGlobalScope('organizador')];
        $eventos = Evento::withoutGlobalScope('organizador')->with($comLocal)->where('organizador_id', $org->id)
            ->where('status', 'publicado')->where('visibilidade', 'publico')->where('data_fim', '>=', now())
            ->orderBy('data_inicio')->get();

        // "Mostrar eventos passados" (V004): realizados ou encerrados, do mais recente para o mais antigo.
        $passados = collect();
        if (\App\Support\Esquema::temV004() && $org->mostrar_eventos_passados) {
            $passados = Evento::withoutGlobalScope('organizador')->with($comLocal)->where('organizador_id', $org->id)
                ->whereIn('status', ['publicado', 'pausado', 'encerrado'])->where('visibilidade', 'publico')
                ->where('data_fim', '<', now())->orderByDesc('data_inicio')->limit(24)->get();
        }

        self::precosMinimos($eventos);

        return view('publico.organizador', compact('org', 'eventos', 'passados'));
    }

    /** Menor preço entre os lotes públicos à venda ou agendados de cada evento ("A partir de R$ ..." / "Gratuito"). */
    private static function precosMinimos($eventos): void
    {
        $precos = $eventos->isEmpty() ? collect() : \App\Models\Lote::withoutGlobalScope('organizador')->whereIn('evento_id', $eventos->pluck('id'))
            ->where('visibilidade', 'publico')->whereIn('status', ['ativo', 'agendado'])
            ->groupBy('evento_id')->selectRaw('evento_id, MIN(preco) AS menor')->pluck('menor', 'evento_id');
        $eventos->each(fn ($e) => $e->setAttribute('preco_minimo', $precos[$e->id] ?? null));
    }

    /** Link de divulgação (?cupom=CODIGO): guarda o código na sessão para a inscrição. */
    private static function guardarCupom(): void
    {
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->query('cupom', '')));
        if ($codigo !== '') {
            session(['cupom_divulgacao' => mb_substr($codigo, 0, 30)]);
        }
    }

    /** Evento pela URL pública. Privados exigem link de acesso privado, que ainda não existe: 404. */
    public static function buscar(string $slug): Evento
    {
        // Só o código fixo (10 primeiros caracteres do uuid); links pelo nome (slug) não abrem mais (decisão do Bruno, 08/11/2026).
        abort_unless(preg_match('/^[0-9a-f]{10}$/', $slug), 404);
        $codigo = substr($slug, 0, 8).'-'.substr($slug, 8, 2);

        return Evento::withoutGlobalScope('organizador')->with('organizador')
            ->where('uuid', 'like', $codigo.'%')
            ->whereIn('status', self::STATUS_PUBLICOS)
            ->where('visibilidade', '<>', 'privado')->firstOrFail();
    }

    /**
     * Lote à venda agora. Enquanto o agendador (cron) não estiver ligado, um lote
     * "agendado" abre quando chega o início das vendas, e um lote com "abre depois de"
     * só abre quando o anterior esgotar, encerrar ou passar do fim das vendas.
     */
    public static function loteAVenda(Evento $evento, $lote): bool
    {
        if ($evento->status !== 'publicado' || $lote->visibilidade !== 'publico' 
            || ! in_array($lote->status, ['ativo', 'agendado'], true)
            || ! $lote->venda_inicio->isPast() || ! $lote->venda_fim->isFuture()
            || ($evento->data_fim && $evento->data_fim->isPast())
            || ($lote->quantidade - $lote->reservado - $lote->vendido) <= 0) {
            return false;
        }

        if ($lote->lote_anterior_id) {
            $anterior = $evento->lotes->firstWhere('id', $lote->lote_anterior_id)
                ?? \App\Models\Lote::withoutGlobalScope('organizador')->find($lote->lote_anterior_id);

            return ! $anterior || in_array($anterior->status, ['esgotado', 'encerrado'], true) || $anterior->venda_fim->isPast()
                || ($anterior->quantidade - $anterior->reservado - $anterior->vendido) <= 0;
        }

        return true;
    }
}
