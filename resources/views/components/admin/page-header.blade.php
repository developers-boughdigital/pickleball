@props(['title', 'subtitle' => null])

<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div>
        <h1 class="text-[28px] leading-tight font-bold tracking-tight sm:text-[32px]">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-2 text-[15px] text-slate-500 sm:text-base">{{ $subtitle }}</p>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-3">{{ $slot }}</div>
    @endif
</div>
