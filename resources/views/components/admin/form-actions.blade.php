{{-- Save bar that floats at the bottom of the screen. The form needs x-data with a `dirty` flag. --}}
<div class="sticky bottom-3 z-10 flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white/90 px-4 py-2.5 shadow-[0_8px_24px_-12px_rgb(15_23_42/0.25)] backdrop-blur">
    <p x-show="dirty" x-transition.opacity x-cloak class="flex items-center gap-2 text-sm text-slate-600">
        <span class="size-2 rounded-full bg-brand-500"></span>
        Unsaved changes
    </p>

    <div class="ml-auto flex items-center gap-2">
        {{ $slot }}
    </div>
</div>
