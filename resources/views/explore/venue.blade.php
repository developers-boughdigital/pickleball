@php
    use App\Models\Venue;

    $back = route('explore', ['tab' => 'venues', ...($drafts ? ['drafts' => 1] : [])]);
    $short = fn (string $url) => preg_replace('~^https?://(www\.)?~', '', rtrim($url, '/'));
    $hasMap = $venue->latitude !== null && $venue->longitude !== null;
    $bbox = $hasMap ? implode(',', [$venue->longitude - 0.012, $venue->latitude - 0.007, $venue->longitude + 0.012, $venue->latitude + 0.007]) : null;
    $facts = array_filter([
        'Courts' => $venue->court_count,
        'Indoor or outdoor' => $venue->indoor_outdoor ? Venue::SETTINGS[$venue->indoor_outdoor] ?? null : null,
        'Surface' => $venue->label('surface'),
        'Court lines' => $venue->label('court_lines'),
    ]);
    $amenities = array_filter(Venue::FACILITIES, fn ($label, $key) => $venue->getAttribute($key) === true, ARRAY_FILTER_USE_BOTH);
@endphp

<x-site :title="$venue->name" :description="'Pickleball at '.$venue->name.($venue->suburb ? ', '.$venue->suburb : '').': courts, facilities and how to book.'">
    <section class="bg-ink-950 text-white">
        <div class="mx-auto max-w-6xl px-4 py-8">
            <a href="{{ $back }}" class="inline-flex items-center gap-1 text-sm text-white/60 hover:text-white">
                <x-icon name="chevron-left" class="size-4" /> All venues
            </a>
            <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold tracking-wide text-brand-300 uppercase">{{ $venue->label('venue_type') ?? 'Place to play' }}</p>
                    <h1 class="mt-1 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $venue->name }}</h1>
                    <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-white/70">
                        @if ($venue->fullAddress()) <span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="size-4" />{{ $venue->fullAddress() }}</span> @endif
                        @if ($drafts && $venue->status !== 'published') <span class="badge-muted">Draft</span> @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($venue->booking_url)
                        <a href="{{ $venue->booking_url }}" target="_blank" rel="noopener" class="inline-flex h-11 items-center gap-2 rounded-xl bg-linear-to-r from-brand-500 to-brand-300 px-5 text-sm font-bold text-ink-950 transition hover:brightness-105">
                            Book a court <x-icon name="external-link" class="size-4" />
                        </a>
                    @endif
                    @if ($venue->mapUrl())
                        <a href="{{ $venue->mapUrl() }}" target="_blank" rel="noopener" class="inline-flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-semibold ring-1 ring-white/25 transition hover:bg-white/10">
                            Directions <x-icon name="external-link" class="size-4" />
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto grid max-w-6xl gap-4 px-4 py-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                <h2 class="font-bold">Courts</h2>
                @if ($facts)
                    <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($facts as $label => $value)
                            <div class="rounded-xl bg-slate-50 p-3">
                                <dt class="text-xs text-slate-500">{{ $label }}</dt>
                                <dd class="mt-0.5 font-semibold">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="mt-2 text-sm text-slate-500">Court details are coming soon.</p>
                @endif

                @if ($amenities)
                    <ul class="mt-4 flex flex-wrap gap-1.5 text-sm font-medium">
                        @foreach ($amenities as $label)
                            <li class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 text-emerald-700"><x-icon name="check" class="size-3.5" />{{ $label }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($hasMap)
                {{-- Loaded on request: the embedded map pulls in a lot and can keep the page "loading" for a long time. --}}
                <section x-data="{ map: false }" class="overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200">
                    <template x-if="map">
                        <iframe
                            title="Map of {{ $venue->name }}"
                            src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&layer=mapnik&marker={{ $venue->latitude }},{{ $venue->longitude }}"
                            class="h-72 w-full border-0"
                        ></iframe>
                    </template>
                    <button x-show="! map" type="button" @click="map = true" class="flex h-40 w-full flex-col items-center justify-center gap-2 bg-slate-50 text-sm font-semibold text-ink-950 transition hover:bg-slate-100">
                        <x-icon name="map-pin" class="size-6 text-brand-600" />
                        Show map
                    </button>
                    <p class="px-4 py-2 text-xs text-slate-500">Map data © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener" class="hover:underline">OpenStreetMap</a> contributors</p>
                </section>
            @endif

            <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                <h2 class="font-bold">Clubs that play here</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @forelse ($venue->clubs as $club)
                        <a href="{{ route('explore.club', [$club, ...($drafts ? ['drafts' => 1] : [])]) }}" class="rounded-xl p-3 ring-1 ring-slate-200 transition hover:bg-slate-50 hover:ring-slate-300">
                            <span class="flex items-center justify-between gap-2">
                                <span class="font-semibold">{{ $club->name }}</span>
                                @if ($club->pivot->is_primary) <span class="badge-success">Home club</span> @endif
                            </span>
                            @if ($club->session_times_text)
                                <span class="mt-0.5 line-clamp-2 block text-sm text-slate-500">{{ $club->session_times_text }}</span>
                            @endif
                        </a>
                    @empty
                        <p class="text-sm text-slate-500">No clubs listed here yet.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-4">
            @if ($venue->court_hire_cents !== null || $venue->free_to_use !== null || $venue->price_notes || $venue->opening_hours_text)
                <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                    <h2 class="font-bold">Prices & hours</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        @if ($venue->free_to_use === true)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Cost</dt><dd class="font-semibold">Free to use</dd></div>
                        @elseif ($venue->court_hire_cents !== null)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Court hire</dt><dd class="font-semibold">{{ $venue->money('court_hire_cents') }} an hour</dd></div>
                        @endif
                        @if ($venue->booking_required !== null)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Booking</dt><dd class="font-semibold">{{ $venue->booking_required ? 'Required' : 'Not needed' }}</dd></div>
                        @endif
                    </dl>
                    @if ($venue->price_notes) <p class="mt-3 text-sm text-slate-600">{{ $venue->price_notes }}</p> @endif
                    @if ($venue->opening_hours_text)
                        <p class="mt-3 inline-flex items-start gap-2 text-sm text-slate-600"><x-icon name="clock" class="mt-0.5 size-4 shrink-0 text-slate-400" />{{ $venue->opening_hours_text }}</p>
                    @endif
                </section>
            @endif

            <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                <h2 class="font-bold">Get in touch</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @if ($venue->phone)
                        <li><a href="tel:{{ preg_replace('/[^\d+]/', '', $venue->phone) }}" class="inline-flex items-center gap-2 hover:underline"><x-icon name="phone" class="size-4 text-slate-400" />{{ $venue->phone }}</a></li>
                    @endif
                    @if ($venue->website)
                        <li><a href="{{ $venue->website }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 break-all hover:underline"><x-icon name="globe" class="size-4 shrink-0 text-slate-400" />{{ $short($venue->website) }}</a></li>
                    @endif
                    @unless ($venue->phone || $venue->website)
                        <li class="text-slate-500">Contact details are coming soon. The clubs above can tell you more.</li>
                    @endunless
                </ul>
            </section>
        </aside>
    </div>
</x-site>
