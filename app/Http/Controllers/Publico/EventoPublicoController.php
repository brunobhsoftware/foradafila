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
        $evento->load(['local', 'categoria', 'tipos' => fn ($q) => $q->where('ativo', 1), 'tipos.lotes']);

        // Só os lotes públicos aparecem; ocultos e de convidados dependem de link próprio (fase futura).
        $lotes = $evento->tipos->flatMap->lotes->where('visibilidade', 'publico')->values();
        $aVenda = $lotes->filter(fn ($l) => self::loteAVenda($evento, $l));

        return view('publico.evento', compact('evento', 'lotes', 'aVenda'));
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
        return Evento::withoutGlobalScope('organizador')->with('organizador')
            ->where('slug', $slug)->whereIn('status', self::STATUS_PUBLICOS)
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
