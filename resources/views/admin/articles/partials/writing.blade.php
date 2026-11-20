{{--
    Claude is writing. "pending" means nobody has started it yet, so this page does;
    "writing" means another tab already did, so this one only waits and checks back.
--}}
<div class="mx-auto max-w-3xl space-y-3"
    x-data="draftWriter(@js(['url' => route('admin.articles.write', $article), 'token' => csrf_token(), 'auto' => $article->writing_status === 'pending']))">
    <x-admin.form-heading icon="bot">
        <h1 class="truncate text-xl font-bold tracking-tight" x-text="headline || @js($article->brief['topic'] ?? $article->title)">{{ $article->brief['topic'] ?? $article->title }}</h1>
        <p class="text-sm text-slate-500">
            <span x-show="state !== 'failed'">Claude is writing the draft. It usually takes two to three minutes.</span>
            <span x-show="state === 'failed'" x-cloak>The draft didn’t finish.</span>
        </p>
    </x-admin.form-heading>

    <section class="card p-4 sm:p-5" x-show="state !== 'failed'">
        <div class="flex items-center justify-between gap-3">
            <p class="flex items-center gap-2 text-sm font-medium">
                <span class="relative flex size-2.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-brand-400 opacity-75"></span><span class="relative inline-flex size-2.5 rounded-full bg-brand-500"></span></span>
                <span x-text="state === 'watching' ? 'Being written. This page checks back every few seconds.' : (state === 'writing' ? 'Writing…' : 'Getting started…')">Getting started…</span>
            </p>
            <p class="text-sm text-slate-500 tabular-nums">
                <span x-show="wordCount" x-cloak><span x-text="wordCount.toLocaleString()"></span> words ·</span>
                <span x-text="clock">0:00</span>
            </p>
        </div>

        <ul class="mt-3 space-y-1 text-sm text-slate-600" x-show="steps.length" x-cloak>
            <template x-for="(step, i) in steps" :key="i">
                <li class="flex items-start gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600" /><span x-text="step"></span></li>
            </template>
        </ul>

        {{-- The draft as it arrives: plain text for now, formatted once it's saved and the editor opens. --}}
        <div x-ref="draft" x-show="article" x-cloak class="mt-3 max-h-[28rem] overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed whitespace-pre-wrap text-slate-700" x-text="article" aria-live="off"></div>

        <p class="mt-3 text-xs text-slate-500">
            You can leave this page. Claude keeps writing, and the finished draft will be waiting in
            <a href="{{ route('admin.articles.index') }}" class="font-medium text-brand-600 hover:underline">the library</a>.
        </p>
    </section>

    <section class="card border-red-200 p-4 sm:p-5" x-show="state === 'failed'" x-cloak>
        <p class="flex items-start gap-2 text-sm font-medium text-red-700"><x-icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" /><span x-text="error"></span></p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="btn-gold" @click="$visit(@js(route('admin.articles.edit', $article)), { push: false })">Change the brief or try again</button>
            <a href="{{ route('admin.articles.index') }}" class="btn-outline">Back to the library</a>
        </div>
    </section>
</div>
