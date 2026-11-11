{{-- The /play/... pages a venue or club appears on, with a note when a level is missing. --}}
@props(['listing'])

@php
    $area = $listing->locationArea();
    $steps = $area ? array_slice($area->trail(), 1) : [];
    $missing = collect(['state' => 'state', 'city' => 'city or region', 'council' => 'council', 'suburb' => 'suburb'])
        ->filter(fn ($label, $field) => blank($listing->{$field}))->values();
    $live = $listing->status === 'published';
@endphp

<section {{ $attributes->class('card p-4 sm:p-5') }}>
    <h2 class="mb-1 text-sm font-semibold">Location pages</h2>
    <p class="mb-3 text-xs text-slate-500">{{ $live ? 'Listed on these pages.' : 'Listed on these pages once it’s published. The links preview it as a draft.' }}</p>

    @forelse ($steps as $step)
        @php($href = $step->url($live ? [] : ['drafts' => 1]))
        <a href="{{ $href }}" target="_blank" data-reload class="flex items-center justify-between gap-3 border-b border-slate-100 py-1.5 text-sm last:border-0 hover:text-brand-600">
            <span><span class="text-slate-500">{{ ucfirst($step->level()) }}</span> · {{ $step->name() }}</span>
            <span class="truncate font-mono text-xs text-slate-500">{{ parse_url($step->url(), PHP_URL_PATH) }}</span>
        </a>
    @empty
        <p class="text-sm text-slate-500">No state yet, so it isn’t on any location page.</p>
    @endforelse

    @if ($missing->isNotEmpty() && $steps)
        <p class="mt-2 text-xs text-amber-600">Missing {{ $missing->join(', ', ' and ') }}, so it stops at the {{ end($steps)->level() }} page. Add it on the edit form, or use Locations › Fill from postcodes.</p>
    @endif
</section>
