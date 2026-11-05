@php
    $distance = $club->distanceKm();
    $fee = match (true) {
        $club->casual_fee_cents !== null => $club->money('casual_fee_cents').' a session',
        $club->membership_fee_cents !== null => $club->money('membership_fee_cents').' a year',
        default => null,
    };
    $highlights = array_filter([
        'Beginners welcome' => $club->beginners_welcome,
        'Coaching' => $club->coaching_available,
        'Equipment provided' => $club->equipment_provided,
        'Juniors' => $club->juniors,
        'DUPR' => $club->dupr_play,
    ]);
@endphp

<article class="group relative flex flex-col rounded-2xl bg-white p-4 ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-[0_12px_28px_-16px_rgb(15_23_42/0.35)] hover:ring-slate-300">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="font-bold leading-snug">
                <a href="{{ route('explore.club', [$club, ...($drafts ? ['drafts' => 1] : [])]) }}" class="after:absolute after:inset-0 focus:outline-none">{{ $club->name }}</a>
            </h2>
            <p class="mt-0.5 text-sm text-slate-500">
                {{ $club->location() ?: 'Australia' }}
                @if ($distance !== null) · <span class="font-medium text-ink-950">{{ $distance < 10 ? number_format($distance, 1) : number_format($distance) }} km</span> @endif
            </p>
        </div>
        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100">
            <x-icon name="users" class="size-5" />
        </span>
    </div>

    @if ($text = $club->short_description ?: $club->session_times_text)
        <p class="mt-3 line-clamp-2 text-sm text-slate-600">{{ $text }}</p>
    @endif

    @if ($club->levels || $highlights)
        <ul class="mt-3 flex flex-wrap gap-1.5 text-xs font-medium">
            @foreach ($club->levels ?? [] as $level)
                <li class="rounded-md bg-slate-100 px-2 py-0.5 text-slate-700">{{ \App\Models\Club::LEVELS[$level] ?? $level }}</li>
            @endforeach
            @foreach (array_keys($highlights) as $label)
                <li class="rounded-md bg-emerald-50 px-2 py-0.5 text-emerald-700">{{ $label }}</li>
            @endforeach
        </ul>
    @endif

    <div class="mt-auto flex items-center justify-between gap-3 border-t border-slate-100 pt-3 text-sm">
        <span class="text-slate-500">
            @if ($club->venues_count)
                Plays at {{ $club->venues_count }} {{ str('venue')->plural($club->venues_count) }}
            @else
                Venue to be confirmed
            @endif
        </span>
        <span class="flex items-center gap-2">
            @if ($drafts && $club->status !== 'published') <span class="badge-muted">Draft</span> @endif
            @if ($fee) <span class="font-semibold">{{ $fee }}</span> @endif
        </span>
    </div>
</article>
