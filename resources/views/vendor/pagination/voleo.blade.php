{{-- Voleo themed paginator. Uses theme.css tokens (--accent, --surface, --border)
     so it reskins automatically. Set as default via AppServiceProvider,
     or use {{ $paginator->links('vendor.pagination.voleo') }}. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación" class="pc-pager">
        <ul class="pc-pager__list">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <li class="pc-pager__item pc-pager__item--disabled" aria-disabled="true">
                    <span class="pc-pager__link"><i class="fa-solid fa-chevron-left"></i></span>
                </li>
            @else
                <li class="pc-pager__item">
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pc-pager__link" aria-label="Anterior">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                </li>
            @endif

            {{-- Page numbers --}}
            @foreach ($elements ?? [] as $element)
                @if (is_string($element))
                    <li class="pc-pager__item pc-pager__item--disabled" aria-disabled="true">
                        <span class="pc-pager__link pc-pager__ellipsis">{{ $element }}</span>
                    </li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="pc-pager__item pc-pager__item--active" aria-current="page">
                                <span class="pc-pager__link">{{ $page }}</span>
                            </li>
                        @else
                            <li class="pc-pager__item">
                                <a href="{{ $url }}" class="pc-pager__link">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <li class="pc-pager__item">
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pc-pager__link" aria-label="Siguiente">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </li>
            @else
                <li class="pc-pager__item pc-pager__item--disabled" aria-disabled="true">
                    <span class="pc-pager__link"><i class="fa-solid fa-chevron-right"></i></span>
                </li>
            @endif
        </ul>

        <div class="pc-pager__meta">
            Mostrando {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} de {{ $paginator->total() }}
        </div>
    </nav>
@endif
