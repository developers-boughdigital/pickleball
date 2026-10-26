{{-- Footer for a paginated table: "Showing 1–15 of 42 users" plus page links. --}}
@props(['paginator', 'noun' => 'item'])

@php
    $last = $paginator->lastPage();
    $current = $paginator->currentPage();
    // Current page ±1, plus the first and last, with gaps shown as "…".
    $pages = collect([1, $current - 1, $current, $current + 1, $last])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
    $link = 'grid size-7 place-items-center rounded-lg border border-slate-200 text-sm hover:bg-slate-50';
@endphp

<div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-sm text-slate-500">
        @if ($paginator->total())
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ number_format($paginator->total()) }} {{ str($noun)->plural($paginator->total()) }}
        @else
            No {{ str($noun)->plural() }}
        @endif
    </p>

    @if ($paginator->hasPages())
        <nav class="flex items-center gap-1.5" aria-label="Pagination">
            @if ($paginator->onFirstPage())
                <span class="{{ $link }} cursor-not-allowed text-slate-300" aria-hidden="true"><x-icon name="chevron-left" class="size-4" /></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="{{ $link }} text-slate-500" aria-label="Previous page"><x-icon name="chevron-left" class="size-4" /></a>
            @endif

            @foreach ($pages as $page)
                @if (! $loop->first && $page - $pages[$loop->index - 1] > 1)
                    <span class="px-0.5 text-slate-400">…</span>
                @endif
                @if ($page === $current)
                    <span class="grid size-7 place-items-center rounded-lg bg-brand-500 text-sm font-medium text-ink-950" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="{{ $link }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="{{ $link }} text-slate-500" aria-label="Next page"><x-icon name="chevron-right" class="size-4" /></a>
            @else
                <span class="{{ $link }} cursor-not-allowed text-slate-300" aria-hidden="true"><x-icon name="chevron-right" class="size-4" /></span>
            @endif
        </nav>
    @endif
</div>
