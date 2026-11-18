{{-- "Vendas encerradas" (decisão do Bruno, 18/11/2026): só pode ser ligado/desligado no dia do evento. --}}
@can('evento.publicar')
    @if (in_array($evento->status, ['publicado', 'pausado']))
        @php
            $encerradas = \App\Support\VendasEncerradas::ativo($evento);
            $podeAlterar = \App\Support\VendasEncerradas::podeAlterar($evento);
        @endphp
        <form method="post" action="{{ route('eventos.vendas-encerradas', $evento) }}" style="display:inline"
            data-confirmar="{{ $encerradas ? 'Reabrir as vendas deste evento? Os ingressos voltam a ser vendidos conforme os lotes.' : 'Encerrar as vendas agora? Ninguém mais consegue comprar, mesmo com ingressos disponíveis. A página mostrará “Vendas encerradas”.' }}">
            @csrf <input type="hidden" name="encerrar" value="{{ $encerradas ? 0 : 1 }}">
            <button class="botao {{ $encerradas ? 'botao--perigo' : 'botao--contorno' }} interruptor-visivel" @disabled(! $podeAlterar)
                title="{{ $podeAlterar ? ($encerradas ? 'Clique para reabrir as vendas' : 'Clique para encerrar as vendas agora') : 'Disponível só no dia do evento' }}">
                <span class="interruptor-visivel__chave {{ $encerradas ? '' : 'ligado' }}" aria-hidden="true"></span>
                {{ $encerradas ? 'Vendas encerradas' : 'Vendas abertas' }}
            </button>
        </form>
    @endif
@endcan
