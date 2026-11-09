{{-- Interruptor "Evento visível" (decisão do Bruno, 09/11/2026): publicado/pausado → ocultar (volta a rascunho); rascunho já publicado antes → mostrar (publica de novo). --}}
@can('evento.publicar')
    @php
        $visivel = in_array($evento->status, ['publicado', 'pausado']);
        $podeMostrar = $evento->status === 'rascunho' && $evento->publicado_em;
    @endphp
    @if ($visivel || $podeMostrar)
        <form method="post" action="{{ route('eventos.status', $evento) }}"
            data-confirmar="{{ $visivel ? 'Ocultar o evento? Ele sai do site e para de vender; os ingressos já emitidos continuam valendo.' : 'Mostrar o evento de novo no site?' }}">
            @csrf <input type="hidden" name="status" value="{{ $visivel ? 'rascunho' : 'publicado' }}">
            <button class="botao botao--escuro interruptor-visivel" aria-pressed="{{ $visivel ? 'true' : 'false' }}" title="{{ $visivel ? 'Clique para ocultar do site' : 'Clique para mostrar no site' }}">
                <span class="interruptor-visivel__chave {{ $visivel ? 'ligado' : '' }}" aria-hidden="true"></span>
                {{ $visivel ? 'Evento visível' : 'Evento oculto' }}
            </button>
        </form>
    @endif
@endcan
