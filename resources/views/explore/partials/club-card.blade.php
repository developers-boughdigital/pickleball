@php
    $distance = $club->distanceKm();
    $fee = match (true) {
        $club->casual_fee_cents !== null => [$club->money('casual_fee_cents'), 'a session'],
        $club->membership_fee_cents !== null => [$club->money('membership_fee_cents'), 'a year'],
        default => null,
    };
    $highlights = array_keys(array_filter([
        'Beginners welcome' => $club->beginners_welcome,
        'Come and try' => $club->come_and_try,
        'Coaching' => $club->coaching_available,
        'Paddles provided' => $club->equipment_provided,
        'Juniors' => $club->juniors,
        'DUPR' => $club->dupr_play,
    ]));
@endphp

<article class="site-card relative flex flex-col gap-3 p-6 transition hover:shadow-[0_12px_32px_rgb(15_27_45/0.14)]">
    <div class="min-w-0">
        <h2 class="site-h3">
            <a href="{{ route('explore.club', [$club, ...($drafts ? ['drafts' => 1] : [])]) }}" class="text-navy no-underline after:absolute after:inset-0 hover:text-court">{{ $club->name }}</a>
        </h2>
        <p class="text-muted">
            {{ $club->location() ?: 'Australia' }}
            @if ($distance !== null) · <span class="font-bold text-navy">{{ $distance < 10 ? number_format($distance, 1) : number_format($distance) }} km</span> @endif
        </p>
    </div>

    <div class="flex flex-wrap gap-2">
        @forelse ($club->levels ?? [] as $level)
            <x-site.level :level="$level" />
        @empty
            <span class="site-tag border-2 border-line-strong bg-white">Levels not listed yet</span>
        @endforelse
    </div>

    @if ($highlights)
        <ul class="flex flex-wrap gap-2">
            @foreach (array_slice($highlights, 0, 3) as $label)
                <li class="site-tag bg-gum-soft text-gum-text">{{ $label }}</li>
            @endforeach
        </ul>
    @endif

    @if ($text = $club->short_description ?: $club->session_times_text)
        <p class="line-clamp-2">{{ $text }}</p>
    @endif

    <div class="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3">
        <span class="text-muted">
            @if ($club->venues_count)
                Plays at {{ $club->venues_count }} {{ str('venue')->plural($club->venues_count) }}
            @else
                Venue to be confirmed
            @endif
        </span>
        <span class="flex items-center gap-2">
            @if ($drafts && $club->status !== 'published') <span class="badge-muted">Draft</span> @endif
            @if ($fee) <span><span class="font-display text-[22px] font-bold">{{ $fee[0] }}</span> <span class="text-muted">{{ $fee[1] }}</span></span> @endif
        </span>
    </div>
</article>
