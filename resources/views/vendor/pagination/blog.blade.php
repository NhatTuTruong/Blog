@if ($paginator->hasPages())
    <div class="flex items-center justify-center gap-2">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
                Prev
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:border-blue-400 hover:text-blue-600 transition-all duration-200">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
                Prev
            </a>
        @endif

        {{-- Page Numbers --}}
        @php
            $current = $paginator->currentPage();
            $last = $paginator->lastPage();
            $delta = 2;
            $left = $current - $delta;
            $right = $current + $delta;
            if ($left < 1) $left = 1;
            if ($right > $last) $right = $last;
        @endphp

        @if ($left > 1)
            <a href="{{ $paginator->url(1) }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-blue-50 hover:border-blue-400 hover:text-blue-600 transition-all duration-200">1</a>
            @if ($left > 2)
                <span class="px-2 text-gray-400">...</span>
            @endif
        @endif

        @for ($i = $left; $i <= $right; $i++)
            @if ($i == $current)
                <span class="inline-flex items-center justify-center px-4 py-2 text-sm font-bold text-white bg-gradient-to-r from-blue-600 to-blue-500 border border-blue-500 rounded-lg shadow-sm">
                    {{ $i }}
                </span>
            @else
                <a href="{{ $paginator->url($i) }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-blue-50 hover:border-blue-400 hover:text-blue-600 transition-all duration-200">
                    {{ $i }}
                </a>
            @endif
        @endfor

        @if ($right < $last)
            @if ($right < $last - 1)
                <span class="px-2 text-gray-400">...</span>
            @endif
            <a href="{{ $paginator->url($last) }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-blue-50 hover:border-blue-400 hover:text-blue-600 transition-all duration-200">{{ $last }}</a>
        @endif

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:border-blue-400 hover:text-blue-600 transition-all duration-200">
                Next
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
            </a>
        @else
            <span class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed">
                Next
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
            </span>
        @endif
    </div>
@endif
