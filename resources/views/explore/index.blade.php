@php
    use App\Models\Club;
    use App\Models\Venue;

    $keep = $drafts ? ['drafts' => 1] : [];
    $here = array_filter(['state' => $state, 'area' => $area]);
    $dollars = fn (int $cents) => '$'.number_format($cents / 100, $cents % 100 ? 2 : 0);
    $plural = fn (int $count, string $word) => number_format($count).' '.str($word)->plural($count);

    // Pins on a plain projection of the area. The outer 2% each way is trimmed so
    // one stray coordinate can't shrink everything else into a corner.
    $lats = $pins->pluck('latitude')->sort()->values();
    $lngs = $pins->pluck('longitude')->sort()->values();
    $edge = fn ($sorted, float $at) => $sorted->isEmpty() ? 0 : $sorted[(int) floor(($sorted->count() - 1) * $at)];
    [$south, $north, $west, $east] = [$edge($lats, 0.02), $edge($lats, 0.98), $edge($lngs, 0.02), $edge($lngs, 0.98)];
    $latSpan = max($north - $south, 0.02);
    $lngSpan = max($east - $west, 0.02);
    $spots = $pins
        ->filter(fn ($venue) => $venue->latitude >= $south && $venue->latitude <= $north && $venue->longitude >= $west && $venue->longitude <= $east)
        ->map(fn ($venue) => [
            'venue' => $venue,
            'x' => 6 + ($venue->longitude - $west) / $lngSpan * 88,
            'y' => 10 + ($north - $venue->latitude) / $latSpan * 72,
        ]);
    $pinSize = $spots->count() > 150 ? 'size-3 border-2' : 'size-5 border-[3px]';

    $areaHeading = match ($areas->first()?->kind) {
        'state' => 'Browse by state',
        'city' => 'Browse by city',
        'council' => 'Browse by council area',
        default => 'Browse by suburb',
    };
    $avatars = ['bg-court text-white', 'bg-gum text-navy', 'bg-court-deep text-white', 'bg-ball text-navy'];
    $initials = fn (string $name) => collect(preg_split('/\s+/', preg_replace('/[^\pL\s]/u', '', $name)))
        ->reject(fn ($word) => in_array(strtolower($word), ['the', 'and', 'of', 'inc', 'club', 'pickleball', '']))
        ->take(3)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('') ?: 'PB';

    $faq = array_filter([
        $stats['indoor'] ? [
            "Where can I play pickleball indoors in {$place}?",
            "{$stats['indoor']} of {$place}’s {$plural($stats['venues'], 'venue')} have indoor courts."
                .($biggest->isNotEmpty() ? ' The largest include '.$biggest->map(fn ($venue) => $venue->name.($venue->suburb ? " in {$venue->suburb}" : ''))->join(', ', ' and ').'.' : ''),
        ] : null,
        $stats['fees']?->low !== null ? [
            'How much does a session cost?',
            $stats['fees']->low === $stats['fees']->high
                ? "Casual club sessions listed here cost {$dollars($stats['fees']->low)}."
                : "Casual club sessions listed here cost {$dollars($stats['fees']->low)} to {$dollars($stats['fees']->high)}. Many clubs also offer a yearly membership.",
        ] : null,
        $stats['outdoor'] ? [
            'Are there outdoor courts?',
            "{$plural($stats['outdoor'], 'venue')} ".($stats['outdoor'] === 1 ? 'has' : 'have').' outdoor courts'
                .($stats['free'] ? ", and {$stats['free']} ".($stats['free'] === 1 ? 'is' : 'are').' free to use.' : '.'),
        ] : null,
        [
            'Do I need my own paddle?',
            'No. Most come and try and beginner sessions lend paddles and balls.'
                .($stats['equipment'] ? " {$plural($stats['equipment'], 'club')} here ".($stats['equipment'] === 1 ? 'says' : 'say').' they provide equipment.' : ''),
        ],
        [
            'How do I know which level to play?',
            'Clubs list their sessions by level, from New to Open. If you are unsure, start with a beginner or come and try session and ask the club where you fit.',
        ],
    ]);
@endphp

<x-site :title="'Pickleball in '.$place" :description="'Pickleball in '.$place.': '.$plural($stats['venues'], 'venue').' and '.$plural($stats['clubs'], 'club').', with courts, sessions, fees and coaches.'">
    {{-- Hero --}}
    <section class="border-b border-line bg-white">
        <div class="site-wrap pt-6 pb-10">
            <nav aria-label="Breadcrumb" class="flex flex-wrap gap-2 text-muted">
                <a href="{{ route('home') }}" class="site-link font-normal">Home</a><span aria-hidden="true">›</span>
                @if ($state)
                    <a href="{{ route('explore', $keep) }}" class="site-link font-normal">Play</a><span aria-hidden="true">›</span>
                    @if ($area)
                        <a href="{{ route('explore', ['state' => $state, ...$keep]) }}" class="site-link font-normal">{{ Venue::STATE_NAMES[$state] }}</a><span aria-hidden="true">›</span>
                    @endif
                @endif
                <span class="font-bold text-navy">{{ $state ? $place : 'Play' }}</span>
            </nav>

            <div class="mt-5 flex flex-wrap items-end gap-10">
                <div class="min-w-0 flex-[1_1_520px]">
                    <h1 class="font-display text-[40px] leading-[44px] font-bold sm:text-5xl sm:leading-[52px]">Pickleball in {{ $place }}</h1>
                    <p class="mt-4 max-w-[640px] text-lg leading-7 text-muted">
                        {{ $place }} has {{ $plural($stats['venues'], 'pickleball venue') }} and {{ $plural($stats['clubs'], 'club') }}.
                        @if ($stats['venues'] && $stats['indoor'] * 2 > $stats['venues'])
                            Most courts are indoor, so you can play all year.
                        @elseif ($stats['beginners'])
                            {{ $plural($stats['beginners'], 'club') }} welcome beginners.
                        @endif
                    </p>
                    <dl class="mt-6 flex flex-wrap gap-3">
                        @foreach ([
                            ['venues', $stats['venues'], 'bg-court-soft text-court-deep'],
                            ['clubs', $stats['clubs'], 'bg-court-soft text-court-deep'],
                            ['clubs welcome beginners', $stats['beginners'], 'bg-ball-soft text-navy'],
                            ['indoor venues', $stats['indoor'], 'bg-gum-soft text-gum-text'],
                        ] as [$label, $value, $colour])
                            <div class="flex min-w-[120px] flex-col-reverse rounded-xl px-5 py-3 {{ $colour }}">
                                <dt class="font-bold">{{ $label }}</dt>
                                <dd class="font-display text-[32px] leading-9 font-bold">{{ number_format($value) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <form method="GET" action="{{ route('explore.clubs') }}" class="flex min-w-0 flex-[1_1_380px] flex-col gap-3 rounded-[20px] bg-white p-6 shadow-[0_12px_32px_rgb(15_27_45/0.14)]"
                    x-data="{
                        locating: false,
                        problem: '',
                        locate() {
                            if (! navigator.geolocation) { this.problem = 'Your browser can’t share your location.'; return; }
                            this.locating = true;
                            this.problem = '';
                            navigator.geolocation.getCurrentPosition(
                                (spot) => { this.$refs.lat.value = spot.coords.latitude.toFixed(4); this.$refs.lng.value = spot.coords.longitude.toFixed(4); this.$refs.lat.disabled = this.$refs.lng.disabled = false; this.$el.requestSubmit(); },
                                () => { this.locating = false; this.problem = 'We couldn’t get your location. Try a suburb or postcode instead.'; },
                                { timeout: 10000 },
                            );
                        },
                    }">
                    @if ($drafts) <input type="hidden" name="drafts" value="1"> @endif
                    <input type="hidden" name="lat" x-ref="lat" disabled>
                    <input type="hidden" name="lng" x-ref="lng" disabled>
                    <label for="q" class="site-h3">Find a game near you</label>
                    <input id="q" name="q" type="search" placeholder="Suburb, postcode or club name" class="site-field">
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="site-btn-outline min-h-14 flex-[1_1_160px]" @click="locate()" :disabled="locating">
                            <span x-text="locating ? 'Finding you…' : 'Use my location'">Use my location</span>
                        </button>
                        <button class="site-btn-ball min-h-14 flex-[1_1_160px] text-lg">Search</button>
                    </div>
                    <p x-show="problem" x-text="problem" x-cloak class="text-[#b42318]"></p>
                </form>
            </div>
        </div>
    </section>

    @if ($canPreview)
        <div class="site-wrap pt-6">
            <div @class(['flex flex-wrap items-center justify-between gap-2 rounded-xl px-4 py-3', 'bg-ball-soft ring-1 ring-ball' => $drafts, 'bg-white ring-1 ring-line' => ! $drafts])>
                <span>
                    @if ($drafts)
                        <strong>Previewing drafts.</strong> Only you can see listings that aren’t published yet.
                    @else
                        You’re signed in as an admin. Visitors see published listings only.
                    @endif
                </span>
                <a href="{{ route('explore', [...$here, ...($drafts ? [] : ['drafts' => 1])]) }}" class="site-link">{{ $drafts ? 'Show only published' : 'Preview drafts' }}</a>
            </div>
        </div>
    @endif

    {{-- Quick filters --}}
    <div class="site-wrap pt-6">
        <div role="group" aria-label="Quick filters" class="flex flex-wrap gap-3">
            @foreach ([
                ['explore.clubs', ['beginners' => 1], 'Beginner friendly'],
                ['explore.clubs', ['come_and_try' => 1], 'Come and try'],
                ['explore.venues', ['setting' => 'indoor'], 'Indoor'],
                ['explore.venues', ['lights' => 1], 'Night play'],
                ['explore.clubs', ['max_fee' => 15], 'Under $15'],
                ['explore.clubs', ['equipment' => 1], 'Paddles provided'],
                ['explore.venues', ['free' => 1], 'Free courts'],
            ] as [$route, $filter, $label])
                <a href="{{ route($route, [...$here, ...$filter, ...$keep]) }}" class="site-chip">{{ $label }}</a>
            @endforeach
            <a href="{{ route('explore.clubs', [...$here, ...$keep]) }}" class="site-chip border-court text-court">All filters</a>
        </div>
    </div>

    {{-- Map and areas --}}
    <section class="site-wrap pt-8">
        <div class="flex flex-wrap gap-6">
            <div class="site-card relative min-h-[440px] min-w-0 flex-[3_1_560px] overflow-hidden bg-court-soft">
                <div aria-hidden="true" class="absolute inset-0 bg-[linear-gradient(#D5DDE8_1px,transparent_1px),linear-gradient(90deg,#D5DDE8_1px,transparent_1px)] bg-size-[80px_80px] opacity-50"></div>
                @foreach ($spots as $spot)
                    <a href="{{ route('explore.venue', [$spot['venue'], ...$keep]) }}" title="{{ $spot['venue']->name }}{{ $spot['venue']->suburb ? ', '.$spot['venue']->suburb : '' }}" tabindex="-1" aria-hidden="true"
                        class="absolute -translate-1/2 rounded-full border-white transition hover:z-10 hover:scale-150 {{ $pinSize }} {{ $spot['venue']->clubs_exists ? 'bg-gum' : 'bg-court' }}"
                        style="left: {{ round($spot['x'], 2) }}%; top: {{ round($spot['y'], 2) }}%"></a>
                @endforeach
                @if ($spots->isEmpty())
                    <p class="absolute inset-0 grid place-items-center text-muted">No venues on the map yet.</p>
                @endif

                <div class="absolute top-4 left-4 flex overflow-hidden rounded-xl border-2 border-line-strong bg-white">
                    <span class="site-btn rounded-none bg-court text-white">Map</span>
                    <a href="{{ route('explore.venues', [...$here, ...$keep]) }}" class="site-btn rounded-none bg-white text-court">List</a>
                </div>
                <div class="absolute right-4 bottom-4 left-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-4 rounded-xl bg-white px-3.5 py-2">
                        <span class="inline-flex items-center gap-2"><span aria-hidden="true" class="size-3.5 rounded-full bg-court"></span>Venue</span>
                        <span class="inline-flex items-center gap-2"><span aria-hidden="true" class="size-3.5 rounded-full bg-gum"></span>A club plays here</span>
                    </div>
                    <span class="rounded-xl bg-white px-3.5 py-2 text-muted">{{ $plural($spots->count(), 'venue') }} mapped</span>
                </div>
            </div>

            <div class="min-w-0 flex-[2_1_340px]">
                @if ($areas->isNotEmpty())
                    <h2 class="site-h2 mb-4">{{ $areaHeading }}</h2>
                    <ul class="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-3">
                        @foreach ($areas as $row)
                            <li>
                                <a href="{{ route('explore', [...($row->kind === 'state' ? ['state' => $row->name] : ['state' => $state, 'area' => $row->name]), ...$keep]) }}" class="site-card block px-4 py-3.5 text-navy no-underline transition hover:shadow-[0_12px_32px_rgb(15_27_45/0.14)]">
                                    <span class="site-h3 block text-court">{{ $row->kind === 'state' ? $row->name : $row->label }}</span>
                                    <span class="text-muted">{{ $plural($row->venues, 'venue') }}@if ($row->clubs) · {{ $plural($row->clubs, 'club') }}@endif</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('explore.venues', [...$here, ...$keep]) }}" class="site-link mt-2 inline-flex min-h-11 items-center">See every venue{{ $state ? ' in '.Venue::STATE_NAMES[$state] : '' }}</a>
                @elseif ($area)
                    <h2 class="site-h2 mb-4">Around {{ $place }}</h2>
                    <div class="site-card p-6">
                        <p class="text-muted">{{ $plural($stats['venues'], 'venue') }} and {{ $plural($stats['clubs'], 'club') }} list {{ $place }} as their suburb, city or council area.</p>
                        <a href="{{ route('explore', ['state' => $state, ...$keep]) }}" class="site-btn-outline mt-4">All of {{ Venue::STATE_NAMES[$state] }}</a>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- When clubs play --}}
    @if ($sessions->isNotEmpty())
        <section class="site-wrap pt-16">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="site-h2">When clubs play</h2>
                <a href="{{ route('explore.clubs', [...$here, ...$keep]) }}" class="site-link">See all {{ $plural($stats['clubs'], 'club') }}</a>
            </div>
            <p class="mt-2 text-muted">Session times as the clubs publish them. Check with the club before you go.</p>
            <div class="mt-4 grid grid-cols-[repeat(auto-fill,minmax(min(340px,100%),1fr))] gap-6">
                @foreach ($sessions as $club)
                    @php($venue = $club->venues->first())
                    <article class="site-card flex flex-col gap-2.5 p-6">
                        <h3 class="site-h3 font-bold"><a href="{{ route('explore.club', [$club, ...$keep]) }}" class="text-navy no-underline hover:text-court">{{ $club->name }}</a></h3>
                        <p class="text-muted">
                            {{ $venue ? $venue->name.($venue->suburb ? ', '.$venue->suburb : '') : ($club->location() ?: 'Venue to be confirmed') }}
                            @if ($venue?->indoor_outdoor) · {{ Venue::SETTINGS[$venue->indoor_outdoor] }} @endif
                        </p>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($club->levels ?? [] as $level)
                                <x-site.level :level="$level" />
                            @empty
                                <span class="site-tag border-2 border-line-strong bg-white">All levels</span>
                            @endforelse
                            @if ($club->beginners_welcome) <span class="site-tag bg-gum-soft text-gum-text">Beginners welcome</span> @endif
                        </div>
                        <p class="line-clamp-3">{{ $club->session_times_text }}</p>
                        <div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-1">
                            @if ($club->casual_fee_cents !== null)
                                <span><span class="font-display text-[22px] font-bold">{{ $dollars($club->casual_fee_cents) }}</span> <span class="text-muted">casual</span></span>
                            @else
                                <span class="text-muted">Times from the club website</span>
                            @endif
                            @if ($club->booking_url)
                                <a href="{{ $club->booking_url }}" target="_blank" rel="noopener" class="site-btn-primary">Book a spot</a>
                            @else
                                <a href="{{ route('explore.club', [$club, ...$keep]) }}" class="site-btn-outline">Check with club</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Venues --}}
    <section class="site-wrap pt-16">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 class="site-h2">Courts and venues</h2>
            @if ($stats['venues'])
                <a href="{{ route('explore.venues', [...$here, ...$keep]) }}" class="site-link">See all {{ $plural($stats['venues'], 'venue') }}</a>
            @endif
        </div>
        @if ($venues->isEmpty())
            <div class="site-card mt-4 p-6 text-muted">No venues listed here yet. We’re checking listings now, so check back soon.</div>
        @else
            <div class="site-card mt-4 overflow-x-auto">
                <table class="w-full min-w-[820px] border-collapse text-left">
                    <thead>
                        <tr class="border-b-2 border-line text-muted">
                            @foreach (['Venue', 'Suburb', 'Indoor or outdoor', 'Courts', 'Surface', 'Lights', 'Price'] as $heading)
                                <th scope="col" class="px-4 py-3 font-bold">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($venues as $venue)
                            <tr class="border-b border-line last:border-0">
                                <td class="px-4 py-3.5"><a href="{{ route('explore.venue', [$venue, ...$keep]) }}" class="site-link">{{ $venue->name }}</a>@if ($drafts && $venue->status !== 'published') <span class="badge-muted ml-1">Draft</span>@endif</td>
                                <td class="px-4 py-3.5">{{ $venue->suburb ?? '' }}</td>
                                <td @class(['px-4 py-3.5', 'text-muted' => ! $venue->indoor_outdoor])>{{ $venue->indoor_outdoor ? Venue::SETTINGS[$venue->indoor_outdoor] : 'Not listed yet' }}</td>
                                @if ($venue->court_count)
                                    <td class="px-4 py-3.5 font-display text-xl font-bold">{{ $venue->court_count }}</td>
                                @else
                                    <td class="px-4 py-3.5 text-muted">Not listed yet</td>
                                @endif
                                <td @class(['px-4 py-3.5', 'text-muted' => ! $venue->surface])>{{ $venue->label('surface') ?? 'Not listed yet' }}</td>
                                <td @class(['px-4 py-3.5', 'text-muted' => $venue->lights === null])>{{ match ($venue->lights) { true => 'Yes', false => 'No', default => 'Not listed yet' } }}</td>
                                @if ($venue->free_to_use)
                                    <td class="px-4 py-3.5 font-bold text-gum-text">Free</td>
                                @elseif ($venue->court_hire_cents !== null)
                                    <td class="px-4 py-3.5">From {{ $dollars($venue->court_hire_cents) }}</td>
                                @else
                                    <td class="px-4 py-3.5 text-muted">Check venue</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Clubs --}}
    <section class="site-wrap pt-16">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 class="site-h2">Clubs in {{ $place }}</h2>
            @if ($stats['clubs'])
                <a href="{{ route('explore.clubs', [...$here, ...$keep]) }}" class="site-link">See all {{ $plural($stats['clubs'], 'club') }}</a>
            @endif
        </div>
        @if ($clubs->isEmpty())
            <div class="site-card mt-4 p-6 text-muted">No clubs listed yet. Check back soon.</div>
        @else
            <div class="mt-4 grid grid-cols-[repeat(auto-fill,minmax(min(340px,100%),1fr))] gap-6">
                @foreach ($clubs as $club)
                    @php($highlights = array_filter(['Beginners welcome' => $club->beginners_welcome, 'Come and try' => $club->come_and_try, 'Coaching' => $club->coaching_available]))
                    <article class="site-card flex flex-col gap-3 p-6">
                        <div class="flex items-center gap-3.5">
                            <div aria-hidden="true" class="grid size-14 shrink-0 place-items-center rounded-xl font-display text-[22px] font-bold {{ $avatars[$loop->index % count($avatars)] }}">{{ $initials($club->name) }}</div>
                            <div class="min-w-0">
                                <h3 class="site-h3">{{ $club->name }}</h3>
                                <p class="text-muted">
                                    {{ $club->suburb ?: ($club->state ?: 'Australia') }}
                                    · {{ $club->venues_count === 1 ? $club->venues->first()->name : ($club->venues_count ? $plural($club->venues_count, 'venue') : 'Venue to be confirmed') }}
                                    @if ($drafts && $club->status !== 'published') <span class="badge-muted ml-1">Draft</span> @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($club->levels ?? [] as $level)
                                <x-site.level :level="$level" />
                            @empty
                                <span class="site-tag border-2 border-line-strong bg-white">Levels not listed yet</span>
                            @endforelse
                        </div>
                        @if ($highlights)
                            <div class="flex flex-wrap gap-2">
                                @foreach (array_keys($highlights) as $label)
                                    <span class="site-tag bg-gum-soft text-gum-text">{{ $label }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if ($club->session_times_text)
                            <p class="line-clamp-2">{{ $club->session_times_text }}</p>
                        @else
                            <p class="text-muted">Session times not listed yet</p>
                        @endif
                        @if ($club->casual_fee_cents !== null || $club->membership_fee_cents !== null)
                            <p>
                                @if ($club->casual_fee_cents !== null)<span class="font-display text-[22px] font-bold">{{ $dollars($club->casual_fee_cents) }}</span> <span class="text-muted">casual</span>@endif
                                @if ($club->casual_fee_cents !== null && $club->membership_fee_cents !== null)<span class="text-muted"> · </span>@endif
                                @if ($club->membership_fee_cents !== null)<span class="font-display text-[22px] font-bold">{{ $dollars($club->membership_fee_cents) }}</span> <span class="text-muted">a year</span>@endif
                            </p>
                        @else
                            <p class="text-muted">Fees not listed yet</p>
                        @endif
                        <div class="mt-auto flex flex-wrap items-center gap-3">
                            <a href="{{ route('explore.club', [$club, ...$keep]) }}" class="site-btn-primary">See club</a>
                            @if (in_array($club->claim_status, ['not_contacted', 'invited']))
                                <a href="{{ route('login') }}" class="site-link">Run this club? Claim it</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Coaches (placeholders until coaches are in the database) --}}
    <section class="site-wrap pt-16">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 class="site-h2">Coaches</h2>
            <a href="{{ route('explore.coaches') }}" class="site-link">See all coaches</a>
        </div>
        <div class="mt-4 grid grid-cols-[repeat(auto-fill,minmax(min(320px,100%),1fr))] gap-6">
            @foreach ($coaches as $coach)
                <a href="{{ route('explore.coaches') }}" class="site-card flex gap-4 p-6 text-navy no-underline transition hover:shadow-[0_12px_32px_rgb(15_27_45/0.14)]">
                    <span aria-hidden="true" class="grid size-16 shrink-0 place-items-center rounded-full font-display text-[22px] font-bold {{ ['bg-court-soft text-court-deep', 'bg-gum-soft text-gum-text', 'bg-ball-soft text-navy'][$loop->index % 3] }}">{{ $coach['initials'] }}</span>
                    <span class="flex flex-col gap-1.5">
                        <span class="site-h3">{{ $coach['name'] }}</span>
                        <span class="text-muted">{{ App\Support\SampleCoaches::ACCREDITATIONS[$coach['accreditation']] }} · {{ $coach['suburb'] }}</span>
                        <span class="flex flex-wrap gap-2">@foreach (array_slice($coach['levels'], 0, 2) as $level)<x-site.level :level="$level" />@endforeach</span>
                        <span>From <strong>{{ $dollars($coach['price_cents']) }}</strong> {{ $coach['unit'] === 'hr' ? 'a lesson' : 'a person' }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Beginners --}}
    <section class="site-wrap pt-16">
        <div class="flex flex-wrap items-center gap-8 rounded-[20px] bg-gum-soft p-6 sm:p-10">
            <div class="min-w-0 flex-[2_1_420px]">
                <h2 class="font-display text-4xl leading-[42px] font-bold">Never played? Start here.</h2>
                <p class="mt-3 text-lg leading-7">
                    Come and try sessions run most weeks. Paddles are usually provided and nobody minds if you miss a shot.
                    @if ($stats['beginners'])
                        {{ $plural($stats['beginners'], 'club') }} in {{ $place }} {{ $stats['beginners'] === 1 ? 'welcomes' : 'welcome' }} beginners.
                    @endif
                </p>
            </div>
            <div class="flex flex-[1_1_260px] flex-col gap-3">
                <a href="{{ route('explore.clubs', [...$here, 'beginners' => 1, ...$keep]) }}" class="site-btn-primary min-h-14 text-lg">Find a beginner-friendly club</a>
                <a href="{{ route('explore.coaches', ['levels' => ['new']]) }}" class="site-btn-outline min-h-14 text-lg">Book a first lesson</a>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="site-wrap pt-16">
        <h2 class="site-h2 mb-4">Pickleball in {{ $place }}: common questions</h2>
        <div class="site-card px-6 py-2">
            @foreach ($faq as [$question, $answer])
                <details @class(['py-4', 'border-b border-line' => ! $loop->last]) @if ($loop->first) open @endif>
                    <summary class="site-h3 cursor-pointer list-none">{{ $question }}</summary>
                    <p class="mt-2.5">{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- Other places --}}
    <section class="site-wrap pt-16 pb-16">
        <h2 class="site-h2 mb-4">{{ $state ? 'Other states' : 'Play by state' }}</h2>
        <div class="flex flex-wrap gap-3">
            @foreach ($states->where('state', '!=', $state) as $row)
                <a href="{{ route('explore', ['state' => $row->state, ...$keep]) }}" class="site-chip">{{ Venue::STATE_NAMES[$row->state] }}</a>
            @endforeach
            @if ($state)
                <a href="{{ route('explore', $keep) }}" class="site-chip border-court text-court">All of Australia</a>
            @endif
        </div>
    </section>
</x-site>
