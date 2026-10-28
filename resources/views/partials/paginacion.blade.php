{{-- Paginador del dashboard: reemplaza el de Laravel, que usa clases de Tailwind que no se compilan aquí. --}}
@if ($paginator->hasPages())
    <nav class="paginador" role="navigation" aria-label="Paginación">
        <p class="paginador-info">
            Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
            · {{ number_format($paginator->firstItem(), 0, ',', '.') }}–{{ number_format($paginator->lastItem(), 0, ',', '.') }}
            de {{ number_format($paginator->total(), 0, ',', '.') }}
        </p>

        <div class="paginador-paginas">
            @if ($paginator->onFirstPage())
                <span class="paginador-item" aria-disabled="true">‹ Anterior</span>
            @else
                <a class="paginador-item" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Anterior</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="paginador-puntos">…</span>
                @else
                    @foreach ($element as $pagina => $url)
                        @if ($pagina == $paginator->currentPage())
                            <span class="paginador-item" aria-current="page">{{ $pagina }}</span>
                        @else
                            <a class="paginador-item" href="{{ $url }}" aria-label="Ir a la página {{ $pagina }}">{{ $pagina }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="paginador-item" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente ›</a>
            @else
                <span class="paginador-item" aria-disabled="true">Siguiente ›</span>
            @endif
        </div>
    </nav>
@endif
