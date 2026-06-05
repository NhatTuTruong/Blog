@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="pagination-nav">
        <ul class="pagination-list" style="list-style:none;margin:0;padding:0;">
            {{-- Previous --}}
            <li>
            @if ($paginator->onFirstPage())
                <span class="pagination-item pagination-disabled">{{ __('pagination.previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pagination-item">{{ __('pagination.previous') }}</a>
            @endif
            </li>

            {{-- Page Numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                <li><span class="pagination-item pagination-ellipsis">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                    <li>
                        @if ($page == $paginator->currentPage())
                            <span class="pagination-item pagination-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pagination-item" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    </li>
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            <li>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pagination-item">{{ __('pagination.next') }}</a>
            @else
                <span class="pagination-item pagination-disabled">{{ __('pagination.next') }}</span>
            @endif
            </li>
        </ul>
        <p class="pagination-info">
            {!! __('Showing') !!}
            @if ($paginator->firstItem())
                <strong>{{ $paginator->firstItem() }}</strong> {!! __('to') !!} <strong>{{ $paginator->lastItem() }}</strong>
            @else
                {{ $paginator->count() }}
            @endif
            {!! __('of') !!} <strong>{{ $paginator->total() }}</strong> {!! __('results') !!}
        </p>
    </nav>
@endif
