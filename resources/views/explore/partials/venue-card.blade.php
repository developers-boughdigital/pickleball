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
        $venue->free_to_use === true => ['Free', 'to use'],
        $venue->court_hire_cents !== null => [$venue->money('court_hire_cents'), 'an hour'],
        default => null,
    };
@endphp

<article class="site-card relative flex flex-col gap-3 p-6 transition hover:shadow-[0_12px_32px_rgb(15_27_45/0.14)]">
    <div class="min-w-0">
        <h2 class="site-h3">
            <a href="{{ route('explore.venue', [$venue, ...($drafts ? ['drafts' => 1] : [])]) }}" class="text-navy no-underline after:absolute after:inset-0 hover:text-court">{{ $venue->name }}</a>
        </h2>
        <p class="truncate text-muted">{{ $venue->fullAddress() ?: 'Address to be confirmed' }}</p>
        @if ($distance !== null)
            <p class="font-bold">{{ $distance < 10 ? number_format($distance, 1) : number_format($distance) }} km away</p>
        @endif
    </div>

    @if ($facts || $amenities)
        <ul class="flex flex-wrap gap-2">
            @foreach ($facts as $fact)
                <li class="site-tag bg-court-soft text-court-deep">{{ $fact }}</li>
            @endforeach
            @foreach ($amenities as $amenity)
                <li class="site-tag gap-1 bg-gum-soft text-gum-text"><x-icon name="check" class="size-4" />{{ $amenity }}</li>
            @endforeach
        </ul>
    @endif

    <div class="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3">
        <span class="text-muted">
            @if ($venue->clubs_count)
                {{ $venue->clubs_count }} {{ str('club')->plural($venue->clubs_count) }} {{ $venue->clubs_count === 1 ? 'plays' : 'play' }} here
            @else
                No clubs listed yet
            @endif
        </span>
        <span class="flex items-center gap-2">
            @if ($drafts && $venue->status !== 'published') <span class="badge-muted">Draft</span> @endif
            @if ($price) <span><span class="font-display text-[22px] font-bold">{{ $price[0] }}</span> <span class="text-muted">{{ $price[1] }}</span></span> @endif
        </span>
    </div>
</article>
