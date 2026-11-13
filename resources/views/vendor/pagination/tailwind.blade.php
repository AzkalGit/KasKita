@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginasi" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-mute">
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
        </p>

        <div class="flex flex-wrap items-center gap-1 text-sm font-semibold">
            @if ($paginator->onFirstPage())
                <span class="rounded-lg border border-line px-3 py-1.5 text-mute opacity-50">‹ Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="rounded-lg border border-line bg-white px-3 py-1.5 text-ink transition hover:bg-soft">‹ Sebelumnya</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-mute">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="rounded-lg bg-brand px-3 py-1.5 text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Ke halaman {{ $page }}"
                               class="rounded-lg border border-line bg-white px-3 py-1.5 text-ink transition hover:bg-soft">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="rounded-lg border border-line bg-white px-3 py-1.5 text-ink transition hover:bg-soft">Berikutnya ›</a>
            @else
                <span class="rounded-lg border border-line px-3 py-1.5 text-mute opacity-50">Berikutnya ›</span>
            @endif
        </div>
    </nav>
@endif