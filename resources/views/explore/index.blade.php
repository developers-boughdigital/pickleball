@php
    use App\Models\Club;
    use App\Models\Venue;

    // Same page with some query values changed; empty values drop out of the URL.
    $url = fn (array $changes = []) => route('explore', array_filter([...request()->except('page'), ...$changes], fn ($value) => filled($value) && $value !== false));
    // Switching tab keeps what you searched for, not the other tab's filters.
    $tabUrl = fn (string $to) => route('explore', array_filter([
        'tab' => $to === 'clubs' ? null : $to,
        'q' => $filters['q'],
        'state' => $filters['state'],
        'lat' => $near['lat'] ?? null,
        'lng' => $near['lng'] ?? null,
        'drafts' => $drafts ? 1 : null,
    ]));
    $noun = $tab === 'clubs' ? 'club' : 'venue';
    $active = collect($filters)->except('q')->filter()->isNotEmpty() || $filters['q'];
@endphp

<x-site :title="$tab === 'clubs' ? 'Find a pickleball club' : 'Find a place to play pickleball'" description="Pickleball clubs and courts across Australia: sessions, levels, fees and where to play.">
    <form
        method="GET"
        action="{{ route('explore') }}"
        x-data="{
            locating: false,
            problem: '',
            locate() {
                if (! navigator.geolocation) { this.problem = 'Your browser can’t share your location.'; return; }
                this.locating = true;
                this.problem = '';
                navigator.geolocation.getCurrentPosition(
                    (spot) => { this.$refs.lat.value = spot.coords.latitude.toFixed(4); this.$refs.lng.value = spot.coords.longitude.toFixed(4); this.$el.requestSubmit(); },
                    () => { this.locating = false; this.problem = 'We couldn’t get your location. Try searching by suburb or postcode instead.'; },
                    { timeout: 10000 },
                );
            },
        }"
        @change="$event.target.type !== 'search' && $el.requestSubmit()"
    >
        @if ($tab !== 'clubs') <input type="hidden" name="tab" value="{{ $tab }}"> @endif
        @if ($drafts) <input type="hidden" name="drafts" value="1"> @endif
        <input type="hidden" name="lat" x-ref="lat" value="{{ $near['lat'] ?? '' }}">
        <input type="hidden" name="lng" x-ref="lng" value="{{ $near['lng'] ?? '' }}">

        <section class="bg-ink-950 pb-6 text-white">
            <div class="mx-auto max-w-6xl px-4 pt-8 sm:pt-12">
                <p class="text-sm font-semibold tracking-wide text-brand-300 uppercase">Pickleball across Australia</p>
                <h1 class="mt-2 max-w-2xl text-3xl font-extrabold tracking-tight sm:text-4xl">
                    {{ $tab === 'clubs' ? 'Find a club to play with' : 'Find a court near you' }}
                </h1>
                <p class="mt-2 max-w-2xl text-white/70">
                    {{ $tab === 'clubs' ? 'Social sessions, coaching and competition, from first-timers to DUPR leagues.' : 'Indoor halls, outdoor courts and leisure centres, with what’s there and how to book.' }}
                </p>

                <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                    <label class="relative flex-1">
                        <span class="sr-only">Search</span>
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-slate-400" />
                        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ $tab === 'clubs' ? 'Club name, suburb or postcode' : 'Venue name, suburb or postcode' }}" class="h-12 w-full rounded-xl border-0 bg-white pr-4 pl-11 text-base text-ink-950 shadow-sm ring-1 ring-white/10 placeholder:text-slate-400 focus:ring-2 focus:ring-brand-400 focus:outline-none">
                    </label>
                    <button type="button" @click="locate()" :disabled="locating" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold ring-1 ring-white/25 transition hover:bg-white/10 disabled:opacity-60">
                        <x-icon name="map-pin" class="size-4 text-brand-300" />
                        <span x-text="locating ? 'Finding you…' : 'Near me'">Near me</span>
                    </button>
                    <button class="inline-flex h-12 items-center justify-center rounded-xl bg-linear-to-r from-brand-500 to-brand-300 px-6 text-sm font-bold text-ink-950 shadow-[0_6px_16px_-6px_rgb(238_176_8/0.8)] transition hover:brightness-105">Search</button>
                </div>
                <p x-show="problem" x-text="problem" x-cloak class="mt-2 text-sm text-amber-300"></p>

                <nav class="mt-6 flex gap-1" aria-label="What to explore">
                    @foreach (['clubs' => 'Clubs', 'venues' => 'Venues'] as $key => $label)
                        <a href="{{ $tabUrl($key) }}" @class([
                            'inline-flex items-center gap-2 rounded-t-xl px-4 py-2.5 text-sm font-semibold transition',
                            'bg-slate-50 text-ink-950' => $tab === $key,
                            'text-white/70 hover:bg-white/10 hover:text-white' => $tab !== $key,
                        ]) @if ($tab === $key) aria-current="page" @endif>
                            <x-icon :name="$key === 'clubs' ? 'users' : 'map-pin'" class="size-4" />
                            {{ $label }}
                            <span @class(['rounded-full px-1.5 text-xs tabular-nums', 'bg-brand-200 text-ink-950' => $tab === $key, 'bg-white/10' => $tab !== $key])>{{ number_format($counts[$key]) }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>
        </section>

        <div class="mx-auto max-w-6xl px-4 py-5">
            @if ($canPreview)
                <div @class(['mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl px-4 py-2.5 text-sm', 'bg-amber-50 text-amber-900 ring-1 ring-amber-200' => $drafts, 'bg-white ring-1 ring-slate-200' => ! $drafts])>
                    <span>
                        @if ($drafts)
                            <strong>Previewing drafts.</strong> Only you can see listings that aren’t published yet; they’re marked “Draft”.
                        @else
                            You’re signed in as an admin. Visitors see published listings only.
                        @endif
                    </span>
                    <a href="{{ $url(['drafts' => $drafts ? null : 1]) }}" class="font-semibold text-brand-700 hover:underline">{{ $drafts ? 'Show only published' : 'Preview drafts' }}</a>
                </div>
            @endif

            {{-- Filters --}}
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="State">
                    @foreach (['' => 'All states', ...array_combine(Venue::STATES, Venue::STATES)] as $value => $label)
                        <label class="cursor-pointer rounded-full bg-white px-3 py-1.5 text-sm font-medium text-slate-600 ring-1 ring-slate-200 transition hover:ring-slate-300 has-checked:bg-ink-950 has-checked:text-white has-checked:ring-ink-950 has-focus-visible:ring-2 has-focus-visible:ring-brand-400">
                            <input type="radio" name="state" value="{{ $value }}" @checked((string) $filters['state'] === (string) $value) class="sr-only">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                @if ($tab === 'clubs')
                    <select name="level" aria-label="Level" class="h-9 cursor-pointer rounded-lg border-0 bg-white pr-8 pl-3 font-medium ring-1 ring-slate-200">
                        <option value="">Any level</option>
                        @foreach (Club::LEVELS as $value => $label)
                            <option value="{{ $value }}" @selected($filters['level'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @foreach (['beginners' => 'Beginners welcome', 'coaching' => 'Coaching'] as $name => $label)
                        <label class="flex h-9 cursor-pointer items-center gap-2 rounded-lg bg-white px-3 font-medium ring-1 ring-slate-200 has-checked:bg-emerald-50 has-checked:text-emerald-800 has-checked:ring-emerald-300">
                            <input type="checkbox" name="{{ $name }}" value="1" @checked($filters[$name]) class="rounded accent-emerald-600">
                            {{ $label }}
                        </label>
                    @endforeach
                @else
                    <select name="setting" aria-label="Indoor or outdoor" class="h-9 cursor-pointer rounded-lg border-0 bg-white pr-8 pl-3 font-medium ring-1 ring-slate-200">
                        <option value="">Indoor & outdoor</option>
                        @foreach (['indoor' => 'Indoor', 'outdoor' => 'Outdoor', 'both' => 'Both indoor and outdoor'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['setting'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="flex h-9 cursor-pointer items-center gap-2 rounded-lg bg-white px-3 font-medium ring-1 ring-slate-200 has-checked:bg-emerald-50 has-checked:text-emerald-800 has-checked:ring-emerald-300">
                        <input type="checkbox" name="lights" value="1" @checked($filters['lights']) class="rounded accent-emerald-600">
                        Lights for night play
                    </label>
                @endif

                @if ($active || $near)
                    <a href="{{ route('explore', array_filter(['tab' => $tab === 'clubs' ? null : $tab, 'drafts' => $drafts ? 1 : null])) }}" class="ml-auto font-medium text-slate-500 hover:text-ink-950">Clear all</a>
                @endif
            </div>
        </div>
    </form>

    <div class="mx-auto max-w-6xl px-4 pb-12">
        <p class="text-sm text-slate-500">
            <strong class="text-ink-950">{{ number_format($results->total()) }}</strong> {{ str($noun)->plural($results->total()) }}
            @if ($near)
                · nearest first · <a href="{{ $url(['lat' => null, 'lng' => null]) }}" class="font-medium text-brand-700 hover:underline">Stop using my location</a>
            @endif
        </p>

        @if ($results->isEmpty())
            <div class="mt-4 rounded-2xl bg-white px-6 py-14 text-center ring-1 ring-slate-200">
                <x-icon :name="$tab === 'clubs' ? 'users' : 'map-pin'" class="mx-auto size-10 text-slate-300" />
                <p class="mt-3 font-semibold">
                    @if ($active)
                        No {{ str($noun)->plural() }} match that search.
                    @else
                        No {{ str($noun)->plural() }} listed yet.
                    @endif
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    @if ($active)
                        Try a nearby suburb, another state, or fewer filters.
                    @elseif ($canPreview && ! $drafts)
                        Listings appear here once they’re checked and published. <a href="{{ $url(['drafts' => 1]) }}" class="font-medium text-brand-700 hover:underline">Preview the drafts</a>.
                    @else
                        We’re checking listings now. Check back soon.
                    @endif
                </p>
            </div>
        @else
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($results as $listing)
                    @include($tab === 'clubs' ? 'explore.partials.club-card' : 'explore.partials.venue-card', [$noun => $listing])
                @endforeach
            </div>

            @if ($results->hasPages())
                <nav class="mt-6 flex items-center justify-between gap-3 text-sm" aria-label="Pages">
                    <span class="text-slate-500">{{ number_format($results->firstItem()) }}–{{ number_format($results->lastItem()) }} of {{ number_format($results->total()) }}</span>
                    <div class="flex gap-2">
                        @if ($results->onFirstPage())
                            <span class="rounded-lg px-3 py-2 text-slate-400 ring-1 ring-slate-200">Previous</span>
                        @else
                            <a href="{{ $results->previousPageUrl() }}" class="rounded-lg bg-white px-3 py-2 font-medium ring-1 ring-slate-200 hover:ring-slate-300">Previous</a>
                        @endif
                        @if ($results->hasMorePages())
                            <a href="{{ $results->nextPageUrl() }}" class="rounded-lg bg-white px-3 py-2 font-medium ring-1 ring-slate-200 hover:ring-slate-300">Next</a>
                        @else
                            <span class="rounded-lg px-3 py-2 text-slate-400 ring-1 ring-slate-200">Next</span>
                        @endif
                    </div>
                </nav>
            @endif
        @endif
    </div>
</x-site>
