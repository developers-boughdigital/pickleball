@php
    use App\Models\Club;
    use App\Models\Venue;

    $route = "explore.{$tab}";
    // Same page with some query values changed; empty values drop out of the URL.
    $url = fn (array $changes = []) => route($route, array_filter([...request()->except('page'), ...$changes], fn ($value) => filled($value) && $value !== false));
    $noun = $tab === 'clubs' ? 'club' : 'venue';
    $active = collect($filters)->filter()->isNotEmpty();
    $place = $area->level() === 'australia' ? null : $area->name();
    $here = $area->query();
    $title = $tab === 'clubs' ? 'Pickleball clubs' : 'Courts and venues';

    // One indexable page per area (and page number); searches and filters are for people, not Google.
    $canonical = route($route, [...$here, ...($results->currentPage() > 1 ? ['page' => $results->currentPage()] : [])]);
    $filtered = collect(request()->except(['page', 'state', 'city', 'council', 'suburb']))->filter(fn ($value) => filled($value))->isNotEmpty();
    $crumbs = [['Home', route('home')], ...collect($area->trail())->map(fn ($step) => [$step->level() === 'australia' ? 'Play' : $step->name(), $step->url()])->all(), [$tab === 'clubs' ? 'Clubs' : 'Venues', $canonical]];
    $schema = [\App\Support\Seo\Schema::itemList($title.($place ? ' in '.$place : ''), $results->getCollection()->map(fn ($listing) => [
        'name' => $listing->name,
        'url' => route($tab === 'clubs' ? 'explore.club' : 'explore.venue', $listing),
    ]))];
@endphp

<x-site :title="$title.($place ? ' in '.$place : '').($results->currentPage() > 1 ? ' (page '.$results->currentPage().')' : '')"
    :description="$tab === 'clubs'
        ? 'Pickleball clubs'.($place ? ' in '.$place : ' across Australia').': social play, coaching and competition, with levels, session times and fees.'
        : 'Pickleball courts and venues'.($place ? ' in '.$place : ' across Australia').': indoor halls and outdoor courts, with facilities, prices and how to book.'"
    :canonical="$canonical" :noindex="$filtered || $drafts" page-type="CollectionPage" :breadcrumbs="$crumbs" :schema="$schema">
    <form
        method="GET"
        action="{{ route($route) }}"
        x-data="nearMe('Try searching by suburb or postcode instead.')"
        @change="$event.target.type !== 'search' && $el.requestSubmit()"
    >
        @if ($drafts) <input type="hidden" name="drafts" value="1"> @endif
        @foreach (['city', 'council', 'suburb'] as $level)
            @if ($filters[$level]) <input type="hidden" name="{{ $level }}" value="{{ $filters[$level] }}" data-area> @endif
        @endforeach
        <input type="hidden" name="lat" x-ref="lat" value="{{ $near['lat'] ?? '' }}">
        <input type="hidden" name="lng" x-ref="lng" value="{{ $near['lng'] ?? '' }}">

        <section class="border-b border-line bg-white">
            <div class="site-wrap pt-6 pb-8">
                <nav aria-label="Breadcrumb" class="flex flex-wrap gap-2 text-muted">
                    <a href="{{ route('home') }}" class="site-link font-normal">Home</a><span aria-hidden="true">›</span>
                    @foreach ($area->trail() as $step)
                        <a href="{{ $step->url($drafts ? ['drafts' => 1] : []) }}" class="site-link font-normal">{{ $step->level() === 'australia' ? 'Play' : $step->name() }}</a><span aria-hidden="true">›</span>
                    @endforeach
                    <span class="font-bold text-navy">{{ $tab === 'clubs' ? 'Clubs' : 'Venues' }}</span>
                </nav>

                <div class="mt-5 flex flex-wrap items-end gap-8">
                    <div class="min-w-0 flex-[1_1_420px]">
                        <h1 class="font-display text-[40px] leading-[44px] font-bold sm:text-5xl sm:leading-[52px]">{{ $title }}{{ $place ? ' in '.$place : '' }}</h1>
                        <p class="mt-3 max-w-[620px] text-lg leading-7 text-muted">
                            {{ $tab === 'clubs' ? 'Social sessions, coaching and competition, from first-timers to DUPR leagues.' : 'Indoor halls, outdoor courts and leisure centres, with what’s there and how to book.' }}
                        </p>
                    </div>
                    <div class="flex min-w-0 flex-[1_1_420px] flex-wrap items-end gap-3">
                        <div class="flex flex-[1_1_240px] flex-col gap-1.5">
                            <label for="q" class="font-bold">Search</label>
                            <input id="q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ $tab === 'clubs' ? 'Club name, suburb or postcode' : 'Venue name, suburb or postcode' }}" class="site-field">
                        </div>
                        <button type="button" @click="locate()" :disabled="locating" class="site-btn-outline min-h-14">
                            <x-icon name="map-pin" class="size-5" />
                            <span x-text="locating ? 'Finding you…' : 'Near me'">Near me</span>
                        </button>
                        <button class="site-btn-ball min-h-14 px-7 text-lg">Search</button>
                    </div>
                </div>
                <p x-show="problem" x-text="problem" x-cloak class="mt-2 text-[#b42318]"></p>

                <nav class="mt-6 flex flex-wrap gap-2" aria-label="What to explore">
                    @foreach (['clubs' => 'Clubs', 'venues' => 'Venues'] as $key => $label)
                        <a href="{{ route("explore.{$key}", array_filter(['q' => $filters['q'], ...$here, 'lat' => $near['lat'] ?? null, 'lng' => $near['lng'] ?? null, 'drafts' => $drafts ? 1 : null])) }}"
                            @class(['site-chip', 'site-chip-on' => $tab === $key]) @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('explore.coaches') }}" class="site-chip">Coaches</a>
                </nav>
            </div>
        </section>

        <div class="site-wrap py-6">
            @if ($canPreview)
                <div @class(['mb-5 flex flex-wrap items-center justify-between gap-2 rounded-xl px-4 py-3', 'bg-ball-soft ring-1 ring-ball' => $drafts, 'bg-white ring-1 ring-line' => ! $drafts])>
                    <span>
                        @if ($drafts)
                            <strong>Previewing drafts.</strong> Only you can see listings that aren’t published yet; they’re marked “Draft”.
                        @else
                            You’re signed in as an admin. Visitors see published listings only.
                        @endif
                    </span>
                    <a href="{{ $url(['drafts' => $drafts ? null : 1]) }}" class="site-link">{{ $drafts ? 'Show only published' : 'Preview drafts' }}</a>
                </div>
            @endif

            <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="State">
                @foreach (['' => 'All states', ...array_combine(Venue::STATES, Venue::STATES)] as $value => $label)
                    <label class="site-chip has-checked:border-court has-checked:bg-court-soft has-checked:text-court-deep has-focus-visible:ring-2 has-focus-visible:ring-court">
                        <input type="radio" name="state" value="{{ $value }}" @checked((string) $filters['state'] === (string) $value) @change="$root.querySelectorAll('[data-area]').forEach((el) => el.remove())" class="sr-only">
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                @php($toggle = 'site-chip has-checked:border-gum-text has-checked:bg-gum-soft has-checked:text-gum-text')
                @if ($tab === 'clubs')
                    <select name="level" aria-label="Level" class="min-h-11 cursor-pointer rounded-full border-2 border-line-strong bg-white pr-9 pl-4 font-bold">
                        <option value="">Any level</option>
                        @foreach (Club::LEVELS as $value => $label)
                            <option value="{{ $value }}" @selected($filters['level'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="max_fee" aria-label="Casual fee" class="min-h-11 cursor-pointer rounded-full border-2 border-line-strong bg-white pr-9 pl-4 font-bold">
                        <option value="">Any price</option>
                        @foreach ([10, 15, 20] as $dollars)
                            <option value="{{ $dollars }}" @selected($filters['max_fee'] === $dollars)>${{ $dollars }} or less</option>
                        @endforeach
                    </select>
                    @foreach (['beginners' => 'Beginners welcome', 'come_and_try' => 'Come and try', 'equipment' => 'Paddles provided', 'coaching' => 'Coaching'] as $name => $label)
                        <label class="{{ $toggle }}">
                            <input type="checkbox" name="{{ $name }}" value="1" @checked($filters[$name]) class="size-5 accent-gum-text">
                            {{ $label }}
                        </label>
                    @endforeach
                @else
                    <select name="setting" aria-label="Indoor or outdoor" class="min-h-11 cursor-pointer rounded-full border-2 border-line-strong bg-white pr-9 pl-4 font-bold">
                        <option value="">Indoor & outdoor</option>
                        @foreach (['indoor' => 'Indoor', 'outdoor' => 'Outdoor', 'both' => 'Both indoor and outdoor'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['setting'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @foreach (['lights' => 'Lights for night play', 'free' => 'Free to use'] as $name => $label)
                        <label class="{{ $toggle }}">
                            <input type="checkbox" name="{{ $name }}" value="1" @checked($filters[$name]) class="size-5 accent-gum-text">
                            {{ $label }}
                        </label>
                    @endforeach
                @endif

                @if ($active || $near)
                    <a href="{{ route($route, $drafts ? ['drafts' => 1] : []) }}" class="site-link ml-auto inline-flex min-h-11 items-center">Clear all</a>
                @endif
            </div>
        </div>
    </form>

    <div class="site-wrap pb-16">
        <p class="text-lg">
            <span class="font-display text-[28px] font-bold">{{ number_format($results->total()) }} {{ str($noun)->plural($results->total()) }}</span>
            @if ($filters['city'] || $filters['council'] || $filters['suburb']) <span class="text-muted">in {{ $place }}</span> @endif
            @if ($near)
                <span class="text-muted">· nearest first ·</span> <a href="{{ $url(['lat' => null, 'lng' => null]) }}" class="site-link">Stop using my location</a>
            @endif
        </p>

        @if ($results->isEmpty())
            <div class="site-card mt-4 px-6 py-14 text-center">
                <x-icon :name="$tab === 'clubs' ? 'users' : 'map-pin'" class="mx-auto size-10 text-line-strong" />
                <p class="site-h3 mt-3">
                    @if ($active)
                        No {{ str($noun)->plural() }} match that search.
                    @else
                        No {{ str($noun)->plural() }} listed yet.
                    @endif
                </p>
                <p class="mt-1 text-muted">
                    @if ($active)
                        Try a nearby suburb, another state, or fewer filters.
                    @elseif ($canPreview && ! $drafts)
                        Listings appear here once they’re checked and published. <a href="{{ $url(['drafts' => 1]) }}" class="site-link">Preview the drafts</a>.
                    @else
                        We’re checking listings now. Check back soon.
                    @endif
                </p>
            </div>
        @else
            <div class="mt-4 grid grid-cols-[repeat(auto-fill,minmax(min(340px,100%),1fr))] gap-6">
                @foreach ($results as $listing)
                    @include($tab === 'clubs' ? 'explore.partials.club-card' : 'explore.partials.venue-card', [$noun => $listing])
                @endforeach
            </div>

            @if ($results->hasPages())
                <nav class="mt-8 flex flex-wrap items-center justify-between gap-3" aria-label="Pages">
                    <span class="text-muted">{{ number_format($results->firstItem()) }}–{{ number_format($results->lastItem()) }} of {{ number_format($results->total()) }}</span>
                    <div class="flex gap-2">
                        @if ($results->onFirstPage())
                            <span class="site-btn border-line text-muted">Previous</span>
                        @else
                            <a href="{{ $results->previousPageUrl() }}" class="site-btn-outline">Previous</a>
                        @endif
                        @if ($results->hasMorePages())
                            <a href="{{ $results->nextPageUrl() }}" class="site-btn-primary">Next</a>
                        @else
                            <span class="site-btn border-line text-muted">Next</span>
                        @endif
                    </div>
                </nav>
            @endif
        @endif
    </div>
</x-site>
