@php
    use App\Models\Article;

    $canEdit = auth()->user()->can('articles.update');
    $canWrite = auth()->user()->can('articles.create');
    $previous = $month->copy()->subMonth()->format('Y-m');
    $next = $month->copy()->addMonth()->format('Y-m');
    $isThisMonth = $month->isSameMonth(now());
@endphp

<x-admin.layout title="Editorial calendar" :breadcrumb="['Articles', 'Calendar']">
    <x-admin.page-header title="Editorial calendar" subtitle="What we plan to publish and when. Add a topic, then press Create article on its row when it’s time to write it.">
        <div class="flex items-center gap-1.5">
            <a href="{{ route('admin.articles.calendar', ['month' => $previous]) }}" class="btn-outline px-2" aria-label="Previous month"><x-icon name="chevron-left" class="size-4" /></a>
            <span class="min-w-36 text-center text-sm font-semibold">{{ $month->format('F Y') }}</span>
            <a href="{{ route('admin.articles.calendar', ['month' => $next]) }}" class="btn-outline px-2" aria-label="Next month"><x-icon name="chevron-right" class="size-4" /></a>
            @unless ($isThisMonth) <a href="{{ route('admin.articles.calendar') }}" class="btn-outline">This month</a> @endunless
        </div>
    </x-admin.page-header>

    @if ($canEdit)
        <form method="POST" action="{{ route('admin.articles.calendar.store') }}" class="card mt-4 p-4">
            @csrf
            <h2 class="mb-3 text-sm font-semibold">Plan an article</h2>
            <div class="grid gap-3 md:grid-cols-[10rem_minmax(0,1fr)_12rem]">
                <x-admin.input name="planned_for" label="Publish on" type="date" :value="old('planned_for', ($isThisMonth ? now() : $month)->format('Y-m-d'))" required />
                <x-admin.input name="topic" label="Topic" :value="old('topic')" required maxlength="200" placeholder="Keyword or working title" />
                <div>
                    <label for="plan-category" class="mb-1 flex items-baseline justify-between text-sm font-medium">Category <span class="text-xs font-normal text-slate-400">Optional</span></label>
                    <select id="plan-category" name="article_category_id" class="field w-full">
                        <option value="">None</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('article_category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label for="plan-notes" class="mb-1 flex items-baseline justify-between text-sm font-medium">Notes for the writer <span class="text-xs font-normal text-slate-400">Optional</span></label>
                    <input id="plan-notes" name="notes" value="{{ old('notes') }}" maxlength="3000" class="field w-full" placeholder="Angle, audience, things to cover. These become Claude’s instructions.">
                </div>
                <button class="btn-gold"><x-icon name="plus" class="size-4" /> Add to calendar</button>
            </div>
        </form>
    @endif

    <div class="mt-3 space-y-2">
        @forelse ($rows as $row)
            @php
                $plan = $row['plan'];
                $article = $row['article'];
                $date = $row['date'];
                $overdue = ! $article && $date->isPast() && ! $date->isToday();
            @endphp
            <article class="card p-3 sm:p-4" x-data="{ editing: false }">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    {{-- The date, like a page from a desk calendar --}}
                    <div @class(['flex shrink-0 items-center gap-3 sm:w-28 sm:flex-col sm:items-start sm:gap-0', 'text-red-600' => $overdue])>
                        <p class="text-2xl leading-none font-bold tabular-nums">{{ $date->format('j') }}</p>
                        <p class="text-xs text-slate-500">{{ $date->format('D') }}{{ $article && $article->status !== 'draft' ? ', '.$date->format('g:i A') : '' }}</p>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $row['topic'] }}</span>
                            @if ($article)
                                <span class="{{ $article->statusBadge() }}">{{ $article->isBeingWritten() ? 'Claude is writing' : Article::STATUSES[$article->status] }}</span>
                            @else
                                <span class="{{ $overdue ? 'badge-danger' : 'badge-muted' }}">{{ $overdue ? 'Overdue' : 'Planned' }}</span>
                            @endif
                            @if ($row['category']) <span class="text-xs text-slate-500">{{ $row['category']->name }}</span> @endif
                        </p>
                        @if ($plan?->notes) <p class="mt-0.5 line-clamp-2 text-sm text-slate-600">{{ $plan->notes }}</p> @endif
                        @if ($article && $plan && $article->status === 'draft')
                            <p class="mt-0.5 text-xs text-slate-500">Drafted, not scheduled yet. Planned for {{ $plan->planned_for->format('j M') }}.</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                        @if ($article)
                            <a href="{{ route('admin.articles.edit', $article) }}" class="btn-outline"><x-icon name="pencil" class="size-4" /> Open article</a>
                        @elseif ($canWrite)
                            <a href="{{ route('admin.articles.create', ['plan' => $plan->id]) }}" class="btn-gold"><x-icon name="plus" class="size-4" /> Create article</a>
                        @endif
                        @if ($plan && $canEdit)
                            <button type="button" class="icon-btn-edit" title="Change this row" @click="editing = ! editing"><x-icon name="calendar" class="size-4" /><span class="sr-only">Change the date or topic</span></button>
                            <form method="POST" action="{{ route('admin.articles.calendar.destroy', $plan) }}" @submit="confirm(@js('Remove “'.$plan->topic.'” from the calendar?'.($article ? ' The article itself stays in the library.' : ''))) || $event.preventDefault()">
                                @csrf
                                @method('DELETE')
                                <button class="icon-btn-delete" title="Remove from the calendar"><x-icon name="trash" class="size-4" /><span class="sr-only">Remove from the calendar</span></button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($plan && $canEdit)
                    <form method="POST" action="{{ route('admin.articles.calendar.update', $plan) }}" x-show="editing" x-cloak class="mt-3 grid gap-3 border-t border-slate-100 pt-3 md:grid-cols-[10rem_minmax(0,1fr)_12rem_auto] md:items-end">
                        @csrf
                        @method('PUT')
                        <x-admin.input name="planned_for" :id="'date-'.$plan->id" label="Publish on" type="date" :value="$plan->planned_for->format('Y-m-d')" required />
                        <x-admin.input name="topic" :id="'topic-'.$plan->id" label="Topic" :value="$plan->topic" required maxlength="200" />
                        <div>
                            <label for="category-{{ $plan->id }}" class="mb-1 block text-sm font-medium">Category</label>
                            <select id="category-{{ $plan->id }}" name="article_category_id" class="field w-full">
                                <option value="">None</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected($plan->article_category_id === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn-outline">Save</button>
                        <div class="md:col-span-4">
                            <label for="notes-{{ $plan->id }}" class="mb-1 block text-sm font-medium">Notes for the writer</label>
                            <textarea id="notes-{{ $plan->id }}" name="notes" rows="2" maxlength="3000" class="field h-auto w-full py-2">{{ $plan->notes }}</textarea>
                        </div>
                    </form>
                @endif
            </article>
        @empty
            <div class="card px-4 py-10 text-center text-sm text-slate-500">
                Nothing planned for {{ $month->format('F') }}. {{ $canEdit ? 'Add a topic above to start the month.' : '' }}
            </div>
        @endforelse
    </div>
</x-admin.layout>
