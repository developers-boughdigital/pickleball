@php
    $heading = $tag ? 'Articles tagged “'.$tag->name.'”' : ($category ? $category->name : 'Learn pickleball');
    $intro = $category?->description ?: 'Guides for getting started, the rules, technique and gear, written for players in Australia.';
    $crumbs = array_filter([['Home', route('home')], ['Learn', route('learn')], $category || $tag ? [$tag?->name ?? $category->name, url()->full()] : null]);
@endphp

<x-site :title="$heading.($articles->currentPage() > 1 ? ', page '.$articles->currentPage() : '')" :description="$intro"
    :canonical="route('learn', array_filter(['category' => $category?->slug, 'tag' => $tag?->slug, 'page' => $articles->currentPage() > 1 ? $articles->currentPage() : null]))"
    :noindex="(bool) $tag" :breadcrumbs="array_values($crumbs)">
    <section class="border-b border-line bg-white">
        <div class="site-wrap py-10 sm:py-12">
            <p class="font-bold text-gum-text">@if ($category || $tag) <a href="{{ route('learn') }}" class="site-link">Learn</a> @else Learn @endif</p>
            <h1 class="mt-2 font-display text-[40px] leading-[44px] font-bold sm:text-5xl sm:leading-[52px]">{{ $heading }}</h1>
            <p class="mt-4 max-w-[680px] text-lg leading-7 text-muted">{{ $intro }}</p>

            @if ($categories->count() > 1)
                <nav aria-label="Sections" class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('learn') }}" @class(['site-chip', 'site-chip-on' => ! $category && ! $tag])>All</a>
                    @foreach ($categories as $option)
                        <a href="{{ route('learn', ['category' => $option->slug]) }}" @class(['site-chip', 'site-chip-on' => $category?->is($option)])>{{ $option->name }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </section>

    <section class="site-wrap py-10 pb-16">
        <div class="grid grid-cols-[repeat(auto-fill,minmax(min(320px,100%),1fr))] gap-6">
            @forelse ($articles as $article)
                <article class="site-card relative flex flex-col overflow-hidden transition hover:shadow-[0_12px_32px_rgb(15_27_45/0.14)]">
                    @if ($article->imageUrl())
                        <img src="{{ $article->imageUrl() }}" alt="" loading="lazy" class="aspect-video w-full object-cover">
                    @endif
                    <div class="flex flex-1 flex-col gap-2 p-6">
                        @if ($article->category) <p class="font-bold text-gum-text">{{ $article->category->name }}</p> @endif
                        <h2 class="site-h3">
                            <a href="{{ $article->url() }}" class="text-navy no-underline after:absolute after:inset-0 hover:text-court">{{ $article->title }}</a>
                        </h2>
                        @if ($article->excerpt) <p class="line-clamp-3 text-muted">{{ $article->excerpt }}</p> @endif
                        <p class="mt-auto pt-2 text-muted">{{ $article->published_at->format('j F Y') }} · {{ $article->readingMinutes() }} min read</p>
                    </div>
                </article>
            @empty
                <p class="site-card col-span-full p-8 text-center text-muted">
                    {{ $category || $tag ? 'Nothing here yet.' : 'Our first guides are being written. Check back soon.' }}
                </p>
            @endforelse
        </div>

        @if ($articles->hasPages())
            <nav class="mt-8 flex items-center justify-between gap-3" aria-label="Pages">
                @if ($articles->onFirstPage()) <span></span> @else <a href="{{ $articles->previousPageUrl() }}" class="site-btn-outline">Newer articles</a> @endif
                @if ($articles->hasMorePages()) <a href="{{ $articles->nextPageUrl() }}" class="site-btn-outline">Older articles</a> @endif
            </nav>
        @endif
    </section>
</x-site>
