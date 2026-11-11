@php
    use App\Models\Club;
    use App\Support\SampleCoaches;

    $dollars = fn (int $cents) => '$'.number_format($cents / 100, $cents % 100 ? 2 : 0);
    // Same page with one filter value taken out, for the "applied" chips.
    $without = function (string $key, ?string $value = null) {
        $query = request()->query();
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = array_values(array_diff((array) ($query[$key] ?? []), [$value]));
        }

        return route('explore.coaches', $query);
    };
    $applied = array_filter([
        ...($near ? [['Within '.$filters['distance'].' km', $without('distance')]] : []),
        ...array_map(fn ($key) => [Club::LEVELS[$key], $without('levels', $key)], $filters['levels']),
        ...array_map(fn ($key) => [SampleCoaches::LESSON_TYPES[$key], $without('types', $key)], $filters['types']),
        ...array_map(fn ($key) => [SampleCoaches::FOCUS[$key], $without('focus', $key)], $filters['focus']),
        ...($filters['price'] ? [[SampleCoaches::PRICES[$filters['price']][0], $without('price')]] : []),
        ...array_map(fn ($key) => [SampleCoaches::TIMES[$key], $without('times', $key)], $filters['times']),
        ...array_map(fn ($key) => [SampleCoaches::ACCREDITATIONS[$key], $without('accreditation', $key)], $filters['accreditation']),
        ...($filters['wwcc'] ? [['WWCC verified', $without('wwcc')]] : []),
    ]);
    $types = fn (array $coach) => collect($coach['types'])->map(fn ($type) => str(SampleCoaches::LESSON_TYPES[$type])->before(' ('))
        ->push(collect($coach['times'])->map(fn ($time) => SampleCoaches::TIMES[$time])->join(', ', ' and '))
        ->join(' · ');
@endphp

{{-- Kept out of search (noindex) while the coaches are placeholders; only real clubs go in the structured data. --}}
<x-site title="Find a pickleball coach" description="Private lessons, small groups and clinics near you, filtered by your level."
    :canonical="route('explore.coaches')" noindex page-type="CollectionPage"
    :breadcrumbs="[['Home', route('home')], ['Play', route('play')], ['Coaches', route('explore.coaches')]]"
    :schema="[\App\Support\Seo\Schema::itemList('Pickleball clubs that offer coaching', $coachingClubs->map(fn ($club) => ['name' => $club->name, 'url' => route('explore.club', $club)]))]">
    <form method="GET" action="{{ route('explore.coaches') }}"
        x-data="nearMe('Try a suburb instead.')"
        @change="$event.target.type !== 'text' && $el.requestSubmit()"
    >
        <input type="hidden" name="lat" x-ref="lat" value="{{ $near['lat'] ?? '' }}">
        <input type="hidden" name="lng" x-ref="lng" value="{{ $near['lng'] ?? '' }}">

        {{-- Hero --}}
        <section class="border-b border-line bg-white">
            <div class="site-wrap pt-6 pb-8">
                <nav aria-label="Breadcrumb" class="flex flex-wrap gap-2 text-muted">
                    <a href="{{ route('home') }}" class="site-link font-normal">Home</a><span aria-hidden="true">›</span>
                    <a href="{{ route('play') }}" class="site-link font-normal">Play</a><span aria-hidden="true">›</span>
                    <span class="font-bold text-navy">Coaches</span>
                </nav>
                <div class="mt-5 flex flex-wrap items-end gap-8">
                    <div class="min-w-0 flex-[1_1_480px]">
                        <h1 class="font-display text-[40px] leading-[44px] font-bold sm:text-5xl sm:leading-[52px]">Find a coach who fits your game.</h1>
                        <p class="mt-3.5 max-w-[620px] text-lg leading-7 text-muted">Private lessons, small groups and clinics near you, filtered by your level.</p>
                    </div>
                    <div class="flex min-w-0 flex-[1_1_420px] flex-wrap items-end gap-3">
                        <div class="flex flex-[1_1_240px] flex-col gap-1.5">
                            <label for="where" class="font-bold">Where do you want lessons?</label>
                            <input id="where" type="text" name="where" value="{{ $filters['where'] }}" placeholder="Suburb or state" class="site-field">
                        </div>
                        <button type="button" @click="locate()" :disabled="locating" class="site-btn-outline min-h-14">
                            <x-icon name="map-pin" class="size-5" />
                            <span x-text="locating ? 'Finding you…' : 'Near me'">Near me</span>
                        </button>
                        <button class="site-btn-ball min-h-14 px-7 text-lg">Find a coach</button>
                    </div>
                </div>
                <p x-show="problem" x-text="problem" x-cloak class="mt-2 text-[#b42318]"></p>
            </div>
        </section>

        <div class="site-wrap flex flex-wrap items-start gap-8 pt-8 pb-16">
            {{-- Filters --}}
            <aside aria-label="Filters" class="max-w-full min-w-0 flex-[1_1_280px]">
                <div class="site-card px-6 pt-1 pb-5">
                    <div class="flex items-center justify-between pt-4">
                        <h2 class="site-h2">Filters</h2>
                        <a href="{{ route('explore.coaches') }}" class="site-link inline-flex min-h-11 items-center px-2">Clear all</a>
                    </div>

                    @php
                        $legend = 'mb-2.5 font-display text-[22px] leading-7 font-semibold';
                        $option = 'flex min-h-11 cursor-pointer items-center gap-3';
                        $box = 'size-6 shrink-0 accent-court';
                        $chip = 'site-chip has-checked:border-court has-checked:bg-court-soft has-checked:text-court-deep has-focus-visible:ring-2 has-focus-visible:ring-court';
                    @endphp

                    <fieldset class="border-b border-line py-5" @disabled(! $near)>
                        <legend class="{{ $legend }}">Distance</legend>
                        @foreach ([5, 10, 25, 50] as $km)
                            <label class="{{ $option }} {{ $near ? '' : 'opacity-50' }}"><input type="radio" name="distance" value="{{ $km }}" @checked($filters['distance'] === $km) class="{{ $box }}">Within {{ $km }} km</label>
                        @endforeach
                        @unless ($near)
                            <p class="mt-1 text-muted">Use “Near me” to search by distance.</p>
                        @endunless
                    </fieldset>

                    <fieldset class="border-b border-line py-5">
                        <legend class="{{ $legend }}">Your level</legend>
                        @foreach (Club::LEVELS as $key => $label)
                            <label class="{{ $option }}"><input type="checkbox" name="levels[]" value="{{ $key }}" @checked(in_array($key, $filters['levels'])) class="{{ $box }}"><x-site.level :level="$key" /></label>
                        @endforeach
                    </fieldset>

                    <fieldset class="border-b border-line py-5">
                        <legend class="{{ $legend }}">Lesson type</legend>
                        @foreach (SampleCoaches::LESSON_TYPES as $key => $label)
                            <label class="{{ $option }}"><input type="checkbox" name="types[]" value="{{ $key }}" @checked(in_array($key, $filters['types'])) class="{{ $box }}">{{ $label }}</label>
                        @endforeach
                    </fieldset>

                    <fieldset class="border-b border-line py-5">
                        <legend class="{{ $legend }}">What you want to work on</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach (SampleCoaches::FOCUS as $key => $label)
                                <label class="{{ $chip }}"><input type="checkbox" name="focus[]" value="{{ $key }}" @checked(in_array($key, $filters['focus'])) class="sr-only">{{ $label }}</label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="border-b border-line py-5">
                        <legend class="{{ $legend }}">Price a lesson</legend>
                        <label class="{{ $option }}"><input type="radio" name="price" value="" @checked(! $filters['price']) class="{{ $box }}">Any price</label>
                        @foreach (SampleCoaches::PRICES as $key => [$label])
                            <label class="{{ $option }}"><input type="radio" name="price" value="{{ $key }}" @checked($filters['price'] === $key) class="{{ $box }}">{{ $label }}</label>
                        @endforeach
                    </fieldset>

                    <fieldset class="border-b border-line py-5">
                        <legend class="{{ $legend }}">When you can play</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach (SampleCoaches::TIMES as $key => $label)
                                <label class="{{ $chip }}"><input type="checkbox" name="times[]" value="{{ $key }}" @checked(in_array($key, $filters['times'])) class="sr-only">{{ $label }}</label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="border-b border-line py-5">
                        <legend class="{{ $legend }}">Accreditation</legend>
                        @foreach (SampleCoaches::ACCREDITATIONS as $key => $label)
                            <label class="{{ $option }}"><input type="checkbox" name="accreditation[]" value="{{ $key }}" @checked(in_array($key, $filters['accreditation'])) class="{{ $box }}">{{ $label }}</label>
                        @endforeach
                    </fieldset>

                    <fieldset class="py-5">
                        <legend class="{{ $legend }}">Coaching juniors</legend>
                        <label class="{{ $option }}"><input type="checkbox" name="wwcc" value="1" @checked($filters['wwcc']) class="{{ $box }}">Working With Children Check verified</label>
                    </fieldset>

                    <button class="site-btn-primary min-h-14 w-full text-lg">Show {{ $coaches->count() }} {{ str('coach')->plural($coaches->count()) }}</button>
                </div>
            </aside>

            {{-- Results --}}
            <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-lg leading-7">
                        <span class="font-display text-[28px] font-bold">{{ $coaches->count() }} {{ str('coach')->plural($coaches->count()) }}</span>
                        @if ($near)
                            <span class="text-muted">within {{ $filters['distance'] }} km of you</span>
                        @elseif ($filters['where'])
                            <span class="text-muted">in {{ $filters['where'] }}</span>
                        @endif
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <label for="sort" class="font-bold">Sort by</label>
                        <select id="sort" name="sort" class="min-h-11 rounded-md border-2 border-line-strong bg-white px-3">
                            @foreach (['best' => 'Best match', 'nearest' => 'Nearest', 'price' => 'Lowest price'] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['sort'] === $value) @disabled($value === 'nearest' && ! $near)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($applied)
                    <div role="group" aria-label="Filters applied" class="flex flex-wrap items-center gap-2">
                        @foreach ($applied as [$label, $link])
                            <a href="{{ $link }}" class="site-chip site-chip-on">{{ $label }} <span aria-hidden="true">×</span><span class="sr-only">Remove</span></a>
                        @endforeach
                    </div>
                @endif

                @forelse ($coaches as $coach)
                    <article class="site-card flex flex-wrap gap-6 p-6">
                        <div aria-hidden="true" class="grid size-24 shrink-0 place-items-center rounded-full font-display text-[32px] font-bold {{ ['bg-court-soft text-court-deep', 'bg-gum-soft text-gum-text', 'bg-ball-soft text-navy'][$loop->index % 3] }}">{{ $coach['initials'] }}</div>
                        <div class="flex min-w-0 flex-[1_1_300px] flex-col gap-2.5">
                            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-2">
                                <h2 class="site-h2">{{ $coach['name'] }}</h2>
                                <span class="text-muted">{{ $coach['suburb'] }}@if ($coach['km'] !== null) · {{ number_format($coach['km'], 1) }} km @endif</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="site-tag bg-court-soft text-court-deep">{{ str(SampleCoaches::ACCREDITATIONS[$coach['accreditation']])->replace('Pickleball Australia', 'PA') }}</span>
                                @if ($coach['wwcc'])
                                    <span class="inline-flex items-center gap-1.5 font-bold text-gum-text"><x-icon name="check" class="size-5" />WWCC verified</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2"><span class="text-muted">Teaches</span>@foreach ($coach['levels'] as $level)<x-site.level :level="$level" />@endforeach</div>
                            <p>{{ $coach['bio'] }}</p>
                            <p class="text-muted">{{ $types($coach) }}</p>
                        </div>
                        <div class="flex flex-[0_1_220px] flex-col gap-2.5">
                            <div>
                                <span class="text-muted">{{ $coach['unit'] === 'hr' ? 'Private from' : 'Clinics from' }}</span>
                                <p class="font-display text-[32px] leading-9 font-bold">{{ $dollars($coach['price_cents']) }}<span class="text-lg font-semibold"> /{{ $coach['unit'] }}</span></p>
                                <span class="text-muted">incl. GST</span>
                            </div>
                            <p class="text-muted">{{ $coach['replies'] }}</p>
                            <span class="site-btn border-line text-muted" title="Enquiries open soon">Enquiries open soon</span>
                        </div>
                    </article>
                @empty
                    <div class="site-card px-6 py-14 text-center">
                        <p class="site-h3">No coaches match those filters.</p>
                        <p class="mt-1 text-muted">Try a bigger distance or fewer filters.</p>
                        <a href="{{ route('explore.coaches') }}" class="site-btn-outline mt-4">Clear all filters</a>
                    </div>
                @endforelse

                @if ($coachingClubs->isNotEmpty())
                    <section class="pt-6">
                        <h2 class="site-h2">Clubs that offer coaching</h2>
                        <p class="mt-1 text-muted">Many clubs run lessons and clinics for their players.</p>
                        <div class="mt-4 grid grid-cols-[repeat(auto-fill,minmax(min(300px,100%),1fr))] gap-4">
                            @foreach ($coachingClubs as $club)
                                <a href="{{ route('explore.club', [$club, ...($drafts ? ['drafts' => 1] : [])]) }}" class="site-card flex flex-col gap-1.5 p-5 text-navy no-underline transition hover:shadow-[0_12px_32px_rgb(15_27_45/0.14)]">
                                    <span class="site-h3">{{ $club->name }}</span>
                                    <span class="text-muted">{{ $club->location() ?: 'Australia' }}</span>
                                    @if ($club->coach_names_text)
                                        <span class="line-clamp-2">{{ $club->coach_names_text }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                <div id="list-yourself" class="mt-4 flex flex-wrap items-center justify-between gap-6 rounded-[20px] bg-gum-soft p-8">
                    <div class="min-w-0 flex-[1_1_360px]">
                        <h2 class="site-h2">Coach pickleball? Get found by the right players.</h2>
                        <p class="mt-2">Free listing. Players send enquiries with their level, goal and when they can play.</p>
                    </div>
                    <a href="{{ route('register') }}" class="site-btn-primary min-h-14 text-lg">List yourself as a coach</a>
                </div>
            </div>
        </div>
    </form>
</x-site>
