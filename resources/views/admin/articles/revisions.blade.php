{{-- An article's saved versions: pick one on the left, read it on the right, restore it if it's the one you want. --}}
<x-admin.layout :title="'History: '.$article->title" :breadcrumb="['Articles', 'History']">
    <x-admin.page-header :title="$article->title" subtitle="Every save that changed the words is kept here, newest first. Restoring one keeps the current version too, so it can be undone.">
        <a href="{{ route('admin.articles.edit', $article) }}" class="btn-outline"><x-icon name="chevron-left" class="size-4" /> Back to the editor</a>
    </x-admin.page-header>

    @if ($revisions->isEmpty())
        <div class="card mt-4 px-4 py-10 text-center text-sm text-slate-500">No saved versions yet. The first one is kept when the article is saved.</div>
    @else
        <div class="mt-4 grid gap-3 lg:grid-cols-[19rem_minmax(0,1fr)]">
            <nav class="card max-h-[70vh] overflow-y-auto p-1.5" aria-label="Saved versions">
                @foreach ($revisions as $revision)
                    @php($on = $revision->is($shown))
                    <a href="{{ route('admin.articles.revisions', [$article, 'revision' => $revision->id]) }}" @class([
                        'block rounded-lg px-3 py-2 text-sm transition',
                        'bg-brand-50 ring-1 ring-brand-300' => $on,
                        'hover:bg-slate-50' => ! $on,
                    ]) @if ($on) aria-current="true" @endif>
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-medium">{{ $revision->note }}</span>
                            @if ($loop->first) <span class="badge-success">Current</span> @endif
                        </span>
                        <span class="block text-xs text-slate-500">
                            {{ $revision->created_at->format('j M Y, g:i A') }} · {{ $revision->user?->name ?? 'Claude' }}
                        </span>
                    </a>
                @endforeach
            </nav>

            <article class="card min-w-0 p-4 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div class="min-w-0">
                        <p class="text-xs text-slate-500">{{ $shown->note }} · {{ $shown->created_at->format('j M Y, g:i A') }} · {{ number_format(str_word_count(strip_tags((string) $shown->body))) }} words</p>
                        <h2 class="mt-0.5 text-xl font-bold tracking-tight">{{ $shown->title }}</h2>
                        @if ($shown->excerpt) <p class="mt-1 text-sm text-slate-600">{{ $shown->excerpt }}</p> @endif
                    </div>
                    @can('articles.update')
                        @unless ($shown->is($revisions->first()))
                            <form method="POST" action="{{ route('admin.articles.restore', [$article, $shown]) }}"
                                @submit="confirm(@js('Restore this version?'.($article->isLive() ? ' The article is live, so readers will see it straight away.' : ''))) || $event.preventDefault()">
                                @csrf
                                <button class="btn-gold"><x-icon name="rotate-ccw" class="size-4" /> Restore this version</button>
                            </form>
                        @endunless
                    @endcan
                </div>

                {{-- Saved bodies went through the HTML cleaner, so they are safe to show as they are. Shortcodes stay as typed. --}}
                <div class="article-body mt-4">{!! $shown->body ?: '<p class="text-slate-500">This version has no body.</p>' !!}</div>

                @if ($shown->meta_title || $shown->meta_description)
                    <dl class="mt-4 grid gap-x-6 gap-y-1 border-t border-slate-100 pt-3 text-sm sm:grid-cols-[9rem_1fr]">
                        <dt class="text-slate-500">Search title</dt><dd>{{ $shown->meta_title ?: '—' }}</dd>
                        <dt class="text-slate-500">Search description</dt><dd>{{ $shown->meta_description ?: '—' }}</dd>
                    </dl>
                @endif
            </article>
        </div>
    @endif
</x-admin.layout>
