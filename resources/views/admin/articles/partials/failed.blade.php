{{-- Claude couldn't write the draft and there's nothing to edit yet: show why, and let the brief be changed and sent again. --}}
<form method="POST" action="{{ route('admin.articles.brief', $article) }}" class="mx-auto max-w-3xl space-y-3">
    @csrf
    @method('PUT')

    <x-admin.form-heading icon="bot">
        <h1 class="truncate text-xl font-bold tracking-tight">{{ $article->title }}</h1>
        <p class="text-sm text-slate-500">Claude couldn’t finish this draft.</p>
    </x-admin.form-heading>

    <p class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        <x-icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" />
        {{ $article->writing_error ?? 'Something went wrong while writing.' }}
    </p>

    <section class="card space-y-4 p-4 sm:p-5">
        @include('admin.articles.partials.brief-fields', ['brief' => $article->brief])
    </section>

    <div class="flex flex-col gap-2 sm:flex-row-reverse sm:items-center sm:justify-between">
        <div class="flex flex-row-reverse gap-2">
            <button class="btn-gold flex-1 sm:flex-none" @disabled(! $claudeReady)><x-icon name="rotate-ccw" class="size-4" /> Try again</button>
            <a href="{{ route('admin.articles.index') }}" class="btn-outline flex-1 sm:flex-none">Back to the library</a>
        </div>
        <a href="{{ route('admin.articles.edit', [$article, 'editor' => 1]) }}" class="btn-outline"><x-icon name="pencil" class="size-4" /> Write it myself</a>
    </div>
</form>
