@props(['items'])

@if ($items->hasPages() || $items->total() > 0)
    <div class="pagination">
        @if ($items->onFirstPage())
            <span class="disabled">Previous</span>
        @else
            <a href="{{ $items->previousPageUrl() }}" rel="prev">Previous</a>
        @endif

        @foreach ($items->getUrlRange(max(1, $items->currentPage() - 2), min($items->lastPage(), $items->currentPage() + 2)) as $page => $url)
            @if ($page === $items->currentPage())
                <span class="current">{{ $page }}</span>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if ($items->hasMorePages())
            <a href="{{ $items->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="disabled">Next</span>
        @endif

        <span class="meta">{{ number_format($items->total()) }} {{ \Illuminate\Support\Str::plural('record', $items->total()) }}</span>
    </div>
@endif
