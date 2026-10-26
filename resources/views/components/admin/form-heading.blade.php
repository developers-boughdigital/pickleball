@props(['icon' => null])

<div class="flex items-center gap-3">
    <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-linear-to-br from-brand-300 to-brand-500 font-bold text-ink-950 shadow-[0_6px_16px_-6px_rgb(238_176_8/0.7)]">
        @if ($icon)
            <x-icon :name="$icon" class="size-5" />
        @else
            {{ $tile ?? '' }}
        @endif
    </span>

    <div class="min-w-0">
        {{ $slot }}
    </div>
</div>
