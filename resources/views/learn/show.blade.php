@php
    use App\Support\Seo\Schema;

    $url = $article->url();
    $crumbs = array_values(array_filter([
        ['Home', route('home')],
        ['Learn', route('learn')],
        $article->category ? [$article->category->name, route('learn', ['category' => $article->category->slug])] : null,
        [$article->title, $url],
    ]));
    $description = $article->meta_description ?: $article->excerpt ?: str(strip_tags((string) $article->body))->squish()->limit(155);
@endphp

<x-site :title="$article->meta_title ?: $article->title" :description="$description" :canonical="$url" :noindex="$preview"
    :image="$article->imageUrl()" :breadcrumbs="$crumbs" :schema="[Schema::article($article, $url, $description)]">
    @if ($preview)
        <p class="bg-navy px-4 py-2 text-center font-bold text-white">
            Preview: {{ $article->status === 'scheduled' ? 'goes live '.$article->published_at->format('j M Y, g:i A') : 'this draft isn’t published' }}. Only signed-in editors can see it.
        </p>
    @endif

    <article>
        <header class="border-b border-line bg-white">
            <div class="site-wrap max-w-[820px] pt-6 pb-8">
                <nav aria-label="Breadcrumb" class="flex flex-wrap gap-2 text-muted">
                    <a href="{{ route('home') }}" class="site-link font-normal">Home</a><span aria-hidden="true">›</span>
                    <a href="{{ route('learn') }}" class="site-link font-normal">Learn</a>
                    @if ($article->category)
                        <span aria-hidden="true">›</span>
                        <a href="{{ route('learn', ['category' => $article->category->slug]) }}" class="site-link font-normal">{{ $article->category->name }}</a>
                    @endif
                </nav>
                <h1 class="mt-4 font-display text-[36px] leading-[42px] font-bold sm:text-5xl sm:leading-[54px]">{{ $article->title }}</h1>
                @if ($article->excerpt) <p class="mt-4 text-xl leading-8 text-muted">{{ $article->excerpt }}</p> @endif
                <p class="mt-4 text-muted">
                    @if ($article->author) By <span class="font-bold text-navy">{{ $article->author->name }}</span> · @endif
                    <time datetime="{{ ($article->published_at ?? $article->updated_at)->toW3cString() }}">{{ ($article->published_at ?? $article->updated_at)->format('j F Y') }}</time>
                    · {{ $article->readingMinutes() }} min read
                </p>
            </div>
        </header>

        <div class="site-wrap max-w-[820px] py-8 pb-12">
            @if ($article->imageUrl())
                <img src="{{ $article->imageUrl() }}" alt="" class="mb-8 aspect-video w-full rounded-[20px] object-cover">
            @endif

            {{-- The body was cleaned to a short list of safe tags when it was saved. --}}
            <div class="article-body site-prose">{!! $article->renderedBody() !!}</div>

            @if ($article->tags->isNotEmpty())
                <ul class="mt-10 flex flex-wrap gap-2 border-t border-line pt-6" aria-label="Tags">
                    @foreach ($article->tags as $tag)
                        <li><a href="{{ route('learn', ['tag' => $tag->slug]) }}" class="site-chip" rel="nofollow">{{ $tag->name }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="site-wrap pb-16">
            <h2 class="site-h2">Keep reading</h2>
            <div class="mt-4 grid grid-cols-[repeat(auto-fill,minmax(min(280px,100%),1fr))] gap-6">
                @foreach ($related as $other)
                    <a href="{{ $other->url() }}" class="site-card flex flex-col gap-2 p-6 text-navy no-underline transition hover:shadow-[0_12px_32px_rgb(15_27_45/0.14)]">
                        <span class="site-h3 text-court">{{ $other->title }}</span>
                        @if ($other->excerpt) <span class="line-clamp-2 text-muted">{{ $other->excerpt }}</span> @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-site>
