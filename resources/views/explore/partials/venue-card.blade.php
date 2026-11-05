@php
    $distance = $venue->distanceKm();
    $facts = array_filter([
        $venue->indoor_outdoor ? \App\Models\Venue::SETTINGS[$venue->indoor_outdoor] ?? null : null,
        $venue->court_count ? $venue->court_count.' '.str('court')->plural($venue->court_count) : null,
        $venue->label('surface'),
    ]);
    $amenities = array_keys(array_filter([
        'Lights' => $venue->lights,
        'Toilets' => $venue->toilets,
        'Parking' => $venue->parking,
        'Accessible' => $venue->accessible,
        'Paddle hire' => $venue->paddle_hire,
    ]));
    $price = match (true) {
        $venue->free_to_use === true => 'Free to use',
        $venue->court_hire_cents !== null => $venue->money('court_hire_cents').' an hour',
        default => null,
    };
@endphp

<article class="group relative flex flex-col rounded-2xl bg-white p-4 ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-[0_12px_28px_-16px_rgb(15_23_42/0.35)] hover:ring-slate-300">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-bold leading-snug">
                <a href="{{ route('explore.venue', [$venue, ...($drafts ? ['drafts' => 1] : [])]) }}" class="after:absolute after:inset-0 focus:outline-none">{{ $venue->name }}</a>
            </h2>
            <p class="mt-0.5 truncate text-sm text-slate-500">
                {{ $venue->fullAddress() ?: 'Address to be confirmed' }}
            </p>
        </div>
        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
            <x-icon name="map-pin" class="size-5" />
        </span>
    </div>

    @if ($distance !== null)
        <p class="mt-1 text-sm font-medium">{{ $distance < 10 ? number_format($distance, 1) : number_format($distance) }} km away</p>
    @endif

    @if ($facts || $amenities)
        <ul class="mt-3 flex flex-wrap gap-1.5 text-xs font-medium">
            @foreach ($facts as $fact)
                <li class="rounded-md bg-slate-100 px-2 py-0.5 text-slate-700">{{ $fact }}</li>
            @endforeach
            @foreach ($amenities as $amenity)
                <li class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-emerald-700"><x-icon name="check" class="size-3" />{{ $amenity }}</li>
            @endforeach
        </ul>
    @endif

    <div class="mt-auto flex items-center justify-between gap-3 border-t border-slate-100 pt-3 text-sm">
        <span class="text-slate-500">
            @if ($venue->clubs_count)
                {{ $venue->clubs_count }} {{ str('club')->plural($venue->clubs_count) }} {{ $venue->clubs_count === 1 ? 'plays' : 'play' }} here
            @else
                No clubs listed yet
            @endif
        </span>
        <span class="flex items-center gap-2">
            @if ($drafts && $venue->status !== 'published') <span class="badge-muted">Draft</span> @endif
            @if ($price) <span class="font-semibold">{{ $price }}</span> @endif
        </span>
    </div>
</article>
