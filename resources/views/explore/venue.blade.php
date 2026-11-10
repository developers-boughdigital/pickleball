@php
    use App\Models\Venue;

    $back = route('explore.venues', $drafts ? ['drafts' => 1] : []);
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

    $url = route('explore.venue', $venue);
    $crumbs = [['Home', route('home')], ['Venues', route('explore.venues')], [$venue->name, $url]];
    $where = $venue->suburb ? ', '.$venue->suburb : '';
@endphp

<x-site
    :title="$venue->meta_title ?: $venue->name.' pickleball courts'.$where"
    :description="$venue->meta_description ?: 'Pickleball at '.$venue->name.$where.($venue->court_count ? ': '.$venue->court_count.' '.str('court')->plural($venue->court_count) : '').', facilities, prices and how to book.'"
    :canonical="$url" :noindex="$venue->status !== 'published'" :breadcrumbs="$crumbs" :schema="[\App\Support\Seo\Schema::venue($venue, $url)]">
    <section class="border-b border-line bg-white">
        <div class="site-wrap pt-6 pb-8">
            <nav aria-label="Breadcrumb" class="flex flex-wrap gap-2 text-muted">
                <a href="{{ route('home') }}" class="site-link font-normal">Home</a><span aria-hidden="true">›</span>
                <a href="{{ $back }}" class="site-link font-normal">Venues</a><span aria-hidden="true">›</span>
                <span class="font-bold text-navy" aria-current="page">{{ $venue->name }}</span>
            </nav>
            <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="font-bold text-gum-text">{{ $venue->label('venue_type') ?? 'Place to play' }}</p>
                    <h1 class="mt-1 font-display text-[40px] leading-[44px] font-bold sm:text-5xl sm:leading-[52px]">{{ $venue->name }}</h1>
                    <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-lg text-muted">
                        @if ($venue->fullAddress()) <span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="size-4" />{{ $venue->fullAddress() }}</span> @endif
                        @if ($drafts && $venue->status !== 'published') <span class="badge-muted">Draft</span> @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($venue->booking_url)
                        <a href="{{ $venue->booking_url }}" target="_blank" rel="noopener" class="site-btn-primary">
                            Book a court <x-icon name="external-link" class="size-4" />
                        </a>
                    @endif
                    @if ($venue->mapUrl())
                        <a href="{{ $venue->mapUrl() }}" target="_blank" rel="noopener" class="site-btn-outline">
                            Directions <x-icon name="external-link" class="size-4" />
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="site-wrap grid gap-6 py-8 pb-16 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="site-card p-6">
                <h2 class="site-h2">Courts</h2>
                @if ($facts)
                    <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($facts as $label => $value)
                            <div class="rounded-xl bg-court-soft p-3">
                                <dt class="text-court-deep">{{ $label }}</dt>
                                <dd class="mt-0.5 font-display text-[22px] font-bold text-court-deep">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="mt-2 text-muted">Court details are coming soon.</p>
                @endif

                @if ($amenities)
                    <ul class="mt-4 flex flex-wrap gap-1.5 font-medium">
                        @foreach ($amenities as $label)
                            <li class="inline-flex items-center gap-1 site-tag gap-1 bg-gum-soft text-gum-text"><x-icon name="check" class="size-3.5" />{{ $label }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($hasMap)
                {{-- Loaded on request: the embedded map pulls in a lot and can keep the page "loading" for a long time. --}}
                <section x-data="{ map: false }" class="site-card overflow-hidden">
                    <template x-if="map">
                        <iframe
                            title="Map of {{ $venue->name }}"
                            src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&layer=mapnik&marker={{ $venue->latitude }},{{ $venue->longitude }}"
                            class="h-72 w-full border-0"
                        ></iframe>
                    </template>
                    <button x-show="! map" type="button" @click="map = true" class="flex h-40 w-full flex-col items-center justify-center gap-2 bg-court-soft font-bold text-court transition hover:bg-[#d6e3f8]">
                        <x-icon name="map-pin" class="size-6 text-court" />
                        Show map
                    </button>
                    <p class="px-4 py-2 text-muted">Map data © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener" class="hover:text-court hover:underline">OpenStreetMap</a> contributors</p>
                </section>
            @endif

            <section class="site-card p-6">
                <h2 class="site-h2">Clubs that play here</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @forelse ($venue->clubs as $club)
                        <a href="{{ route('explore.club', [$club, ...($drafts ? ['drafts' => 1] : [])]) }}" class="rounded-xl p-4 text-navy no-underline ring-2 ring-line transition hover:bg-court-soft hover:ring-court">
                            <span class="flex items-center justify-between gap-2">
                                <span class="site-h3 text-court">{{ $club->name }}</span>
                                @if ($club->pivot->is_primary) <span class="badge-success">Home club</span> @endif
                            </span>
                            @if ($club->session_times_text)
                                <span class="mt-0.5 line-clamp-2 block text-muted">{{ $club->session_times_text }}</span>
                            @endif
                        </a>
                    @empty
                        <p class="text-sm text-muted">No clubs listed here yet.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-4">
            @if ($venue->court_hire_cents !== null || $venue->free_to_use !== null || $venue->price_notes || $venue->opening_hours_text)
                <section class="site-card p-6">
                    <h2 class="site-h2">Prices & hours</h2>
                    <dl class="mt-3 space-y-2">
                        @if ($venue->free_to_use === true)
                            <div class="flex justify-between gap-3"><dt class="text-muted">Cost</dt><dd class="font-bold">Free to use</dd></div>
                        @elseif ($venue->court_hire_cents !== null)
                            <div class="flex justify-between gap-3"><dt class="text-muted">Court hire</dt><dd class="font-bold">{{ $venue->money('court_hire_cents') }} an hour</dd></div>
                        @endif
                        @if ($venue->booking_required !== null)
                            <div class="flex justify-between gap-3"><dt class="text-muted">Booking</dt><dd class="font-bold">{{ $venue->booking_required ? 'Required' : 'Not needed' }}</dd></div>
                        @endif
                    </dl>
                    @if ($venue->price_notes) <p class="mt-3 text-navy">{{ $venue->price_notes }}</p> @endif
                    @if ($venue->opening_hours_text)
                        <p class="mt-3 inline-flex items-start gap-2 text-navy"><x-icon name="clock" class="mt-0.5 size-4 shrink-0 text-line-strong" />{{ $venue->opening_hours_text }}</p>
                    @endif
                </section>
            @endif

            <section class="site-card p-6">
                <h2 class="site-h2">Get in touch</h2>
                <ul class="mt-3 space-y-2">
                    @if ($venue->phone)
                        <li><a href="tel:{{ preg_replace('/[^\d+]/', '', $venue->phone) }}" class="inline-flex items-center gap-2 hover:text-court hover:underline"><x-icon name="phone" class="size-4 text-line-strong" />{{ $venue->phone }}</a></li>
                    @endif
                    @if ($venue->website)
                        <li><a href="{{ $venue->website }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 break-all hover:text-court hover:underline"><x-icon name="globe" class="size-4 shrink-0 text-line-strong" />{{ $short($venue->website) }}</a></li>
                    @endif
                    @unless ($venue->phone || $venue->website)
                        <li class="text-muted">Contact details are coming soon. The clubs above can tell you more.</li>
                    @endunless
                </ul>
            </section>
        </aside>
    </div>
</x-site>
