@if ($paginator->hasPages())
    <nav class="paginacao" aria-label="Paginação">
        @if ($paginator->onFirstPage())
            <span class="desab">‹ Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Anterior</a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="desab">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="atual" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima ›</a>
        @else
            <span class="desab">Próxima ›</span>
        @endif
    </nav>
@endif
