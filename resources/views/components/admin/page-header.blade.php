@props(['title', 'subtitle' => null])

<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div>
        <h1 class="text-xl leading-tight font-bold tracking-tight sm:text-2xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-3">{{ $slot }}</div>
    @endif
</div>
