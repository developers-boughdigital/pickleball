@php
    use App\Models\Article;

    $total = $counts->sum();
    $tabs = [null => ['All', $total], 'draft' => ['Drafts', $counts['draft'] ?? 0], 'scheduled' => ['Scheduled', $counts['scheduled'] ?? 0], 'published' => ['Published', $counts['published'] ?? 0]];
    $tabQuery = array_filter(['search' => $filters['search'], 'category' => $filters['category']]);
@endphp

<x-admin.layout title="Articles" :breadcrumb="['Articles', 'Library']">
    <x-admin.page-header title="Articles" subtitle="Everything written for the Learn section: drafts, what’s scheduled and what’s live.">
        <a href="{{ route('admin.articles.calendar') }}" class="btn-outline">
            <x-icon name="calendar" class="size-4" />
            Calendar
        </a>
        @can('articles.create')
            <a href="{{ route('admin.articles.create') }}" class="btn-gold">
                <x-icon name="plus" class="size-4" />
                New article
            </a>
        @endcan
    </x-admin.page-header>

    <nav class="mt-4 flex flex-wrap gap-1.5" aria-label="Article status">
        @foreach ($tabs as $key => [$label, $count])
            @php($on = (string) $filters['status'] === (string) $key)
            <a href="{{ route('admin.articles.index', [...$tabQuery, ...($key ? ['status' => $key] : [])]) }}" @class([
                'inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-sm font-medium transition',
                'bg-ink-950 text-white' => $on,
                'bg-white text-slate-600 ring-1 ring-slate-200 hover:text-ink-950' => ! $on,
            ]) @if ($on) aria-current="page" @endif>
                {{ $label }}
                <span @class(['rounded-full px-1.5 text-xs tabular-nums', 'bg-white/20' => $on, 'bg-slate-100' => ! $on])>{{ $count }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_14rem]">
        @if ($filters['status']) <input type="hidden" name="status" value="{{ $filters['status'] }}"> @endif
        <x-admin.search :value="$filters['search']" placeholder="Search by title or address" />
        <x-admin.select name="category" :options="$categories" :selected="$filters['category']" placeholder="All categories" />
    </form>

    <div class="card mt-3 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Title</th>
                        <th class="px-4 py-2.5 font-medium">Category</th>
                        <th class="px-4 py-2.5 font-medium">Tags</th>
                        <th class="px-4 py-2.5 font-medium">Author</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="px-4 py-2.5 font-medium">Date</th>
                        <th class="px-4 py-2.5 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 border-t border-slate-200">
                    @forelse ($articles as $article)
                        <tr class="transition hover:bg-slate-50/60">
                            <td class="max-w-96 px-4 py-2.5">
                                <a href="{{ route('admin.articles.edit', $article) }}" class="font-medium hover:underline">{{ $article->title }}</a>
                                <p class="flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                                    <span class="truncate font-mono">/learn/{{ $article->slug }}</span>
                                    {{-- On a phone the Status column is off to the right, so say it here too. --}}
                                    <span class="{{ $article->statusBadge() }} md:hidden">{{ Article::STATUSES[$article->status] }}</span>
                                    @if ($article->isBeingWritten())
                                        <span class="badge-pending">Claude is writing</span>
                                    @elseif ($article->writing_status === 'failed')
                                        <span class="badge-danger">Draft failed</span>
                                    @elseif ($article->brief)
                                        <span class="inline-flex items-center gap-1"><x-icon name="bot" class="size-3.5" /> Drafted by Claude</span>
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $article->category?->name ?? '—' }}</td>
                            <td class="max-w-56 px-4 py-2.5">
                                <span class="line-clamp-1 text-xs text-slate-600">{{ $article->tags->pluck('name')->implode(', ') ?: '—' }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $article->author?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5"><span class="{{ $article->statusBadge() }}">{{ Article::STATUSES[$article->status] }}</span></td>
                            <td class="px-4 py-2.5 text-xs whitespace-nowrap text-slate-600">
                                @if ($article->status === 'draft')
                                    Edited {{ $article->updated_at->diffForHumans() }}
                                @else
                                    {{ $article->status === 'scheduled' ? 'Goes live' : 'Published' }}<br>
                                    <span class="font-medium text-ink-950">{{ $article->published_at->format('j M Y, g:i A') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ $article->url() }}" target="_blank" rel="noopener" data-reload class="icon-btn-view" title="{{ $article->isLive() ? 'View on the site' : 'Preview' }}">
                                        <x-icon name="eye" class="size-4" />
                                        <span class="sr-only">{{ $article->isLive() ? 'View' : 'Preview' }} {{ $article->title }}</span>
                                    </a>
                                    @can('articles.update')
                                        <a href="{{ route('admin.articles.edit', $article) }}" class="icon-btn-edit" title="Edit {{ $article->title }}">
                                            <x-icon name="pencil" class="size-4" />
                                            <span class="sr-only">Edit {{ $article->title }}</span>
                                        </a>
                                    @endcan
                                    @can('articles.delete')
                                        <form method="POST" action="{{ route('admin.articles.destroy', $article) }}" @submit="confirm(@js("Delete “{$article->title}”?".($article->isLive() ? ' It is live and will disappear from the site.' : ''))) || $event.preventDefault()">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn-delete" title="Delete {{ $article->title }}">
                                                <x-icon name="trash" class="size-4" />
                                                <span class="sr-only">Delete {{ $article->title }}</span>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                                @if (array_filter($filters))
                                    No articles match.
                                @else
                                    No articles yet. Choose “New article”, give Claude a topic and a few sources, and edit the draft it writes.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$articles" noun="article" />
    </div>
</x-admin.layout>
