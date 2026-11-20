{{-- Review and publish: the editor on the left; publishing, category, tags, image and shortcodes on the right. --}}
@php
    use App\Models\Article;

    $canPublish = auth()->user()->can('articles.publish');
    $live = $article->isLive();
    $slugLocked = $article->status === 'published';
    $chosenTags = collect(old('tags', $article->tags->pluck('id')->all()))->map(fn ($id) => (int) $id);
    $publishAt = old('publish_at', ($article->status === 'scheduled' ? $article->published_at : now()->addDay()->setTime(9, 0))->format('Y-m-d\TH:i'));
    $saveLabel = $live ? 'Save changes' : 'Save draft';
    $tools = [
        ['h2', 'Heading', 'H2'], ['h3', 'Smaller heading', 'H3'], null,
        ['bold', 'Bold', 'B', 'font-bold'], ['italic', 'Italic', 'I', 'italic'], ['underline', 'Underline', 'U', 'underline'], null,
        ['bullets', 'Bullet list', '• List'], ['numbers', 'Numbered list', '1. List'], ['quote', 'Quote', '“ ”'],
    ];
@endphp

<form id="article-form" method="POST" action="{{ route('admin.articles.update', $article) }}" enctype="multipart/form-data"
    x-data="articleEditor(@js(['title' => old('title', $article->title), 'metaTitle' => (string) old('meta_title', $article->meta_title), 'metaText' => (string) old('meta_description', $article->meta_description), 'image' => $article->imageUrl()]))"
    @input="dirty = true" @change="dirty = true">
    @csrf
    @method('PUT')
    {{-- First submit button in the form, so Enter in a text field saves and never publishes. --}}
    <button name="intent" value="save" class="hidden" tabindex="-1" aria-hidden="true"></button>
    <input type="hidden" name="remove_image" :value="removeImage ? 1 : 0" value="0">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-admin.form-heading icon="align-left">
            <h1 class="truncate text-xl font-bold tracking-tight">{{ $live ? 'Edit published article' : 'Review and publish' }}</h1>
            <p class="flex flex-wrap items-center gap-x-2 text-sm text-slate-500">
                <span class="{{ $article->statusBadge() }}">{{ Article::STATUSES[$article->status] }}</span>
                @if ($article->status === 'scheduled')
                    Goes live {{ $article->published_at->format('j M Y, g:i A') }}
                @elseif ($live)
                    Live since {{ $article->published_at->format('j M Y') }}
                @else
                    Last saved {{ $article->updated_at->diffForHumans() }}
                @endif
            </p>
        </x-admin.form-heading>
        <a href="{{ route('admin.articles.index') }}" class="btn-outline"><x-icon name="chevron-left" class="size-4" /> Library</a>
    </div>

    @if ($article->writing_status === 'failed')
        <p class="mt-3 flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            <x-icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" />
            <span>Claude’s last attempt didn’t finish: {{ $article->writing_error }} What’s below is unchanged.</span>
        </p>
    @endif

    @if ($errors->any())
        <p class="mt-3 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            <x-icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" />
            <span>Not saved. {{ $errors->first() }}</span>
        </p>
    @endif

    <div class="mt-3 grid gap-3 xl:grid-cols-[minmax(0,1fr)_21rem]">
        {{-- The article --}}
        <div class="min-w-0 space-y-3">
            <section class="card p-4 sm:p-5">
                <label for="title" class="sr-only">Title</label>
                <input id="title" name="title" x-model="title" required maxlength="255" placeholder="Title"
                    class="w-full border-0 bg-transparent p-0 text-2xl font-bold tracking-tight placeholder:text-slate-300 focus:ring-0 focus:outline-none">

                <div class="mt-2 flex flex-wrap items-center gap-x-1 gap-y-1 text-xs text-slate-500">
                    <label for="slug" class="font-mono">{{ url('learn') }}/</label>
                    <input id="slug" name="slug" value="{{ old('slug', $article->slug) }}" maxlength="120" @readonly($slugLocked)
                        class="field h-7 min-w-0 flex-1 font-mono text-xs read-only:bg-slate-50 read-only:text-slate-500 sm:max-w-md">
                    @if ($slugLocked) <span>Fixed once published, so links keep working.</span> @endif
                </div>
            </section>

            {{-- No overflow-hidden here: it would stop the toolbar sticking to the top of the window. --}}
            <section class="card">
                {{-- Toolbar: stays in view while a long article scrolls. --}}
                <div class="sticky top-14 z-10 flex flex-wrap items-center gap-1 rounded-t-xl border-b border-slate-200 bg-white px-2 py-1.5" x-show="ready" x-cloak role="toolbar" aria-label="Formatting">
                    @foreach ($tools as $tool)
                        @if ($tool === null)
                            <span class="mx-1 h-5 w-px bg-slate-200"></span>
                        @else
                            <button type="button" @click="run('{{ $tool[0] }}')" title="{{ $tool[1] }}" aria-label="{{ $tool[1] }}"
                                class="h-7 min-w-7 rounded-md px-1.5 text-sm text-slate-700 hover:bg-slate-100 {{ $tool[3] ?? '' }}"
                                :class="active.{{ $tool[0] }} && 'bg-brand-100 text-ink-950'" :aria-pressed="active.{{ $tool[0] }} ? 'true' : 'false'">{{ $tool[2] }}</button>
                        @endif
                    @endforeach
                    <button type="button" @click="link()" title="Add or change a link" class="inline-flex h-7 items-center gap-1 rounded-md px-1.5 text-sm text-slate-700 hover:bg-slate-100" :class="active.link && 'bg-brand-100 text-ink-950'">
                        <x-icon name="link" class="size-4" /> Link
                    </button>
                    <button type="button" @click="run('rule')" title="Dividing line" aria-label="Dividing line" class="h-7 min-w-7 rounded-md px-1.5 text-sm text-slate-700 hover:bg-slate-100">—</button>
                    <span class="mx-1 h-5 w-px bg-slate-200"></span>
                    <button type="button" @click="run('undo')" title="Undo" aria-label="Undo" class="grid size-7 place-items-center rounded-md text-slate-700 hover:bg-slate-100"><x-icon name="rotate-ccw" class="size-4" /></button>
                    <button type="button" @click="run('redo')" title="Redo" aria-label="Redo" class="grid size-7 place-items-center rounded-md text-slate-700 hover:bg-slate-100"><x-icon name="rotate-ccw" class="size-4 -scale-x-100" /></button>
                    <span class="ml-auto pr-1 text-xs text-slate-500 tabular-nums" x-text="words.toLocaleString() + (words === 1 ? ' word' : ' words')"></span>
                </div>

                <div x-ref="editor" x-show="ready" x-cloak></div>

                {{-- Holds the saved HTML and is what gets submitted. Stays visible only if the editor can't load. --}}
                <label for="body" class="sr-only">Article body</label>
                <textarea id="body" name="body" x-ref="body" x-show="! ready" rows="18" class="block w-full rounded-xl border-0 p-4 font-mono text-xs focus:ring-0 focus:outline-none" placeholder="Loading the editor…">{{ old('body', $article->body) }}</textarea>
            </section>
            @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <section class="card p-4 sm:p-5">
                <label for="excerpt" class="mb-1 flex items-baseline justify-between gap-2 text-sm font-semibold">Summary <span class="text-xs font-normal text-slate-400">Optional</span></label>
                <textarea id="excerpt" name="excerpt" rows="2" maxlength="500" class="field h-auto w-full py-2" placeholder="One or two sentences shown on the Learn page under the title">{{ old('excerpt', $article->excerpt) }}</textarea>
            </section>

            <section class="card p-4 sm:p-5">
                <h2 class="mb-1 text-sm font-semibold">Search and sharing</h2>
                <p class="mb-3 text-xs text-slate-500">How the article shows in Google and when it’s shared. Left blank, the title and summary are used.</p>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-3">
                        <div>
                            <x-admin.input name="meta_title" label="Search title" x-model="metaTitle" maxlength="70" optional />
                            <p class="mt-0.5 text-right text-xs text-slate-400" x-text="metaTitle.length + ' / 70'"></p>
                        </div>
                        <div>
                            <label for="meta_description" class="mb-1 flex items-baseline justify-between text-sm font-medium">Search description <span class="text-xs font-normal text-slate-400">Optional</span></label>
                            <textarea id="meta_description" name="meta_description" rows="3" maxlength="170" x-model="metaText" class="field h-auto w-full py-2"></textarea>
                            <p class="mt-0.5 text-right text-xs text-slate-400" x-text="metaText.length + ' / 170'"></p>
                            @error('meta_description') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-3" aria-hidden="true">
                        <p class="text-xs text-slate-500">Preview</p>
                        <p class="mt-1 truncate text-xs text-slate-600">{{ $article->url() }}</p>
                        <p class="truncate text-lg leading-snug text-[#1a0dab]" x-text="(metaTitle || title) + ' · {{ config('app.name') }}'"></p>
                        <p class="line-clamp-2 text-sm text-slate-600" x-text="metaText || 'The summary, or the opening of the article, when left blank.'"></p>
                    </div>
                </div>
            </section>
        </div>

        {{-- Publishing and filing --}}
        <aside class="space-y-3">
            <section class="card space-y-3 p-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">Publish</h2>
                    <a href="{{ $article->url() }}" target="_blank" rel="noopener" data-reload class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline"
                        title="{{ $live ? 'Open the live article' : 'Shows the last saved version. Only signed-in editors can see it.' }}">
                        {{ $live ? 'View on the site' : 'Preview' }} <x-icon name="external-link" class="size-3.5" />
                    </a>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button name="intent" value="save" class="btn-outline flex-1"><x-icon name="check" class="size-4" /> {{ $saveLabel }}</button>
                    @if ($canPublish && ! $live)
                        <button name="intent" value="publish" class="btn-gold flex-1"><x-icon name="globe" class="size-4" /> Publish now</button>
                    @endif
                </div>

                @if ($canPublish)
                    @unless ($live)
                        <div class="border-t border-slate-100 pt-3">
                            <label for="publish_at" class="mb-1 block text-sm font-medium">{{ $article->status === 'scheduled' ? 'Change the time' : 'Or publish later' }}</label>
                            <div class="flex gap-2">
                                <input id="publish_at" name="publish_at" type="datetime-local" value="{{ $publishAt }}" class="field min-w-0 flex-1">
                                <button name="intent" value="schedule" class="btn-outline"><x-icon name="clock" class="size-4" /> {{ $article->status === 'scheduled' ? 'Reschedule' : 'Schedule' }}</button>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ str_replace('_', ' ', config('app.timezone')) }} time. It goes live by itself at that time.</p>
                            @error('publish_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endunless

                    @if ($article->status !== 'draft')
                        <button name="intent" value="unpublish" class="text-xs font-medium text-red-600 hover:text-red-800"
                            @click="confirm(@js($live ? 'Take this article off the site? It goes back to draft.' : 'Cancel the schedule and go back to draft?')) || $event.preventDefault()">
                            {{ $live ? 'Unpublish (back to draft)' : 'Cancel the schedule (back to draft)' }}
                        </button>
                    @endif
                @else
                    <p class="text-xs text-slate-500">You can edit and save. Publishing needs the “Publish articles” permission.</p>
                @endif
            </section>

            <section class="card p-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <label for="article_category_id" class="text-sm font-semibold">Category</label>
                    <a href="{{ route('admin.articles.categories') }}" class="text-xs font-medium text-brand-600 hover:underline">Manage</a>
                </div>
                <div class="relative">
                    <select id="article_category_id" name="article_category_id" class="field w-full cursor-pointer appearance-none pr-8">
                        <option value="">No category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('article_category_id', $article->article_category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-500" />
                </div>
            </section>

            <section class="card p-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">Tags</h2>
                    <a href="{{ route('admin.articles.tags') }}" class="text-xs font-medium text-brand-600 hover:underline">Manage</a>
                </div>
                @if ($tags->isEmpty())
                    <p class="text-sm text-slate-500">No tags yet. Add some under Articles › Tags.</p>
                @else
                    @if ($tags->count() > 8)
                        <input type="search" x-model="tagFilter" @input.stop @change.stop placeholder="Find a tag" aria-label="Find a tag" class="field mb-2 w-full">
                    @endif
                    {{-- Sub-tags are indented under their parent. --}}
                    <ul class="max-h-56 space-y-0.5 overflow-y-auto">
                        @foreach ($tags as $tag)
                            <li x-show="tagMatches(@js($tag->path))" style="padding-left: {{ $tag->depth * 1.1 }}rem">
                                <label class="flex cursor-pointer items-center gap-2 rounded px-1 py-0.5 text-sm hover:bg-slate-50">
                                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="size-4 accent-brand-500" @checked($chosenTags->contains($tag->id))>
                                    {{ $tag->name }}
                                </label>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="card p-4">
                <h2 class="mb-2 text-sm font-semibold">Featured image</h2>
                <img x-show="image" :src="image" alt="" class="mb-2 aspect-video w-full rounded-lg object-cover" @if (! $article->imageUrl()) x-cloak @endif src="{{ $article->imageUrl() }}">
                <div class="flex items-center gap-2">
                    <label class="btn-outline h-7 cursor-pointer px-2.5 text-xs">
                        <x-icon name="upload" class="size-3.5" />
                        <span x-text="image ? 'Replace' : 'Upload'">Upload</span>
                        <input type="file" name="featured_image" x-ref="image" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="pickImage">
                    </label>
                    <button type="button" x-show="image" @click="clearImage" class="h-7 px-2 text-xs font-medium text-red-600 hover:text-red-800" @if (! $article->imageUrl()) x-cloak @endif>Remove</button>
                </div>
                <p class="mt-1 text-xs text-slate-500">PNG, JPG or WebP, up to 3 MB. Shown at the top of the article and when it’s shared.</p>
                @error('featured_image') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </section>

            <section class="card p-4" x-data="{ open: false }">
                <button type="button" class="flex w-full items-center justify-between gap-2 text-sm font-semibold" @click="open = ! open" :aria-expanded="open">
                    <span class="inline-flex items-center gap-2"><x-icon name="code" class="size-4 text-slate-500" /> Shortcodes</span>
                    <x-icon name="chevron-down" class="size-4 text-slate-500 transition" ::class="open && 'rotate-180'" />
                </button>
                <div x-show="open" x-cloak class="mt-2">
                    <p class="text-xs text-slate-500">Type one on a line of its own, or insert it where the cursor is. On the site it becomes live content.</p>
                    <ul class="mt-2 divide-y divide-slate-100">
                        @foreach ($shortcodes as $shortcode)
                            <li class="flex items-start justify-between gap-2 py-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">{{ $shortcode['label'] }}</p>
                                    <p class="font-mono text-[11px] break-all text-slate-600">{{ count($shortcode['insert']) > 1 ? $shortcode['insert'][0].' … '.last($shortcode['insert']) : $shortcode['insert'][0] }}</p>
                                    <p class="text-xs text-slate-500">{{ $shortcode['about'] }}</p>
                                </div>
                                <button type="button" class="btn-outline h-7 shrink-0 px-2 text-xs" @click="insert(@js($shortcode['insert']))">Insert</button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            <section class="card space-y-2 p-4 text-sm">
                <a href="{{ route('admin.articles.revisions', $article) }}" class="flex items-center justify-between gap-2 font-semibold hover:text-brand-600">
                    <span class="inline-flex items-center gap-2"><x-icon name="clock" class="size-4 text-slate-500" /> History</span>
                    <span class="text-xs font-normal text-slate-500">{{ $revisionCount }} saved {{ str('version')->plural($revisionCount) }} <x-icon name="chevron-right" class="inline size-3.5" /></span>
                </a>
                @can('articles.update')
                    <button type="button" class="flex items-center gap-2 font-semibold hover:text-brand-600" @click="$dispatch('write-again')"><x-icon name="bot" class="size-4 text-slate-500" /> {{ $article->brief ? 'Write again with Claude' : 'Ask Claude for a draft' }}</button>
                @endcan
                @can('articles.delete')
                    <button form="delete-article" class="flex items-center gap-2 font-semibold text-red-600 hover:text-red-800"
                        @click="confirm(@js("Delete “{$article->title}”?".($live ? ' It is live and will disappear from the site.' : ''))) || $event.preventDefault()">
                        <x-icon name="trash" class="size-4" /> Delete article
                    </button>
                @endcan
            </section>
        </aside>
    </div>

    <div class="mt-3">
        <x-admin.form-actions>
            <button name="intent" value="save" class="btn-gold"><x-icon name="check" class="size-4" /> {{ $saveLabel }}</button>
        </x-admin.form-actions>
    </div>
</form>

@can('articles.delete')
    <form id="delete-article" method="POST" action="{{ route('admin.articles.destroy', $article) }}">
        @csrf
        @method('DELETE')
    </form>
@endcan

{{-- Outside the article form: this one sends the brief, not the article. --}}
@can('articles.update')
    <form id="write-again" method="POST" action="{{ route('admin.articles.brief', $article) }}" class="card mt-3 scroll-mt-20 p-4 sm:p-5" @write-again.window="open = true; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth' }))" x-data="{ open: {{ $errors->hasAny(['topic', 'sources.*', 'instructions']) ? 'true' : 'false' }} }"
        @submit="{{ filled($article->body) ? 'confirm('.Js::from('Replace the article with a new draft from Claude? The current version is kept in History.').') || $event.preventDefault()' : '' }}">
        @csrf
        @method('PUT')
        <button type="button" class="flex w-full items-center justify-between gap-2 text-left" @click="open = ! open" :aria-expanded="open">
            <span>
                <span class="flex items-center gap-2 text-sm font-semibold"><x-icon name="bot" class="size-4 text-slate-500" /> {{ $article->brief ? 'Write again with Claude' : 'Ask Claude for a draft' }}</span>
                <span class="text-xs text-slate-500">{{ $article->brief ? 'Change the brief and get a fresh draft. The current version stays in History.' : 'Give Claude a topic, sources and instructions, and it writes this article.' }}</span>
            </span>
            <x-icon name="chevron-down" class="size-4 shrink-0 text-slate-500 transition" ::class="open && 'rotate-180'" />
        </button>
        <div x-show="open" x-cloak class="mt-4 space-y-4">
            @include('admin.articles.partials.brief-fields', ['brief' => $article->brief ?? ['topic' => $article->title]])
            <button class="btn-gold" @disabled(! $claudeReady)><x-icon name="bot" class="size-4" /> {{ filled($article->body) ? 'Write a new draft' : 'Write the draft' }}</button>
            @unless ($claudeReady) <p class="text-xs text-amber-700">Claude isn’t connected. Add the Anthropic API key in System Settings.</p> @endunless
        </div>
    </form>
@endcan
