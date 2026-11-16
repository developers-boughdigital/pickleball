{{-- The club admin's or venue manager's editor for their own listing. --}}
@php
    use App\Models\Club;
    use App\Models\Venue;

    $value = fn (string $field) => old($field, $listing->getAttribute($field));
    $dollars = fn (string $column) => old(str_replace('_cents', '', $column), $listing->getAttribute($column) === null ? null : rtrim(rtrim(number_format($listing->getAttribute($column) / 100, 2, '.', ''), '0'), '.'));
    $has = fn (string $field) => in_array($field, $fields);
    $yesNo = $kind === 'club'
        ? array_intersect_key(Club::FEATURES, array_flip($fields))
        : array_intersect_key(Venue::FACILITIES, array_flip($fields));

    $sections = $kind === 'club'
        ? [
            'About the club' => ['short_description' => ['Short description', 'textarea', 2, 'One or two sentences for search results and club cards.'], 'description' => ['About', 'textarea', 6, null]],
            'Playing' => ['session_times_text' => ['Session times', 'textarea', 3, 'For example: Tuesdays 7–9pm and Saturdays 9am–12pm, Banyule Leisure Centre']],
            'Fees' => ['casual_fee_cents' => ['Casual fee per session ($)', 'money', null, null], 'membership_fee_cents' => ['Membership per year ($)', 'money', null, null], 'membership_notes' => ['Membership notes', 'textarea', 2, 'Concessions, trial sessions, what’s included'], 'booking_url' => ['Booking or sign-up link', 'url', null, null]],
            'Contact' => ['email' => ['Club email', 'email', null, null], 'phone' => ['Phone', 'tel', null, null], 'website' => ['Website', 'url', null, null], 'facebook' => ['Facebook', 'url', null, null], 'instagram' => ['Instagram', 'url', null, null]],
        ]
        : [
            'Courts' => ['court_count' => ['Number of courts', 'number', null, null], 'indoor_outdoor' => ['Indoor or outdoor', Venue::SETTINGS, null, null], 'surface' => ['Surface', Venue::SURFACES, null, null], 'court_lines' => ['Court lines', Venue::COURT_LINES, null, null]],
            'Prices and hours' => ['court_hire_cents' => ['Court hire per hour ($)', 'money', null, null], 'price_notes' => ['Price notes', 'textarea', 2, 'Peak and off-peak, member rates'], 'opening_hours_text' => ['Opening hours', 'textarea', 3, 'For example: Mon–Fri 6am–10pm, weekends 8am–6pm'], 'booking_url' => ['Booking link', 'url', null, null]],
            'Contact' => ['phone' => ['Phone', 'tel', null, null], 'website' => ['Website', 'url', null, null]],
        ];
@endphp

<x-layout :title="'Edit '.$listing->name">
    <div class="mx-auto max-w-[760px]">
        <p class="flex flex-wrap gap-x-4 gap-y-1">
            <a href="{{ route('account') }}" class="site-link font-normal">← Your account</a>
            <a href="{{ $listingUrl }}" class="site-link font-normal">See the public page</a>
        </p>
        <h1 class="mt-3 font-display text-4xl leading-[42px] font-bold">{{ $listing->name }}</h1>
        <p class="mt-1 text-muted">Changes go live as soon as you save. To change the name, address or location, <a href="{{ $listingUrl }}#tell-us" class="site-link">tell our team</a>.</p>

        @if ($errors->any())
            <p class="mt-4 rounded-xl bg-[#fde8e6] px-4 py-3 font-bold text-[#b42318]">Please check the fields marked below.</p>
        @endif

        <form method="POST" action="{{ route('manage.update', [$kind, $listing]) }}" class="mt-6 space-y-6">
            @csrf
            @method('PUT')

            @foreach ($sections as $heading => $items)
                <section class="site-card p-6">
                    <h2 class="site-h2 mb-4">{{ $heading }}</h2>
                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach ($items as $field => [$label, $type, $rows, $help])
                            @continue(! $has($field))
                            @php($name = $type === 'money' ? str_replace('_cents', '', $field) : $field)
                            <div @class(['flex flex-col gap-1.5', 'sm:col-span-2' => $type === 'textarea' || in_array($field, ['email', 'website', 'booking_url'])])>
                                <label for="{{ $name }}" class="font-bold">{{ $label }}</label>
                                @if ($help) <p class="text-muted" id="{{ $name }}-help">{{ $help }}</p> @endif
                                @if ($type === 'textarea')
                                    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" class="site-field h-auto py-3" @if ($help) aria-describedby="{{ $name }}-help" @endif>{{ $value($field) }}</textarea>
                                @elseif (is_array($type))
                                    <select id="{{ $name }}" name="{{ $name }}" class="site-field">
                                        <option value="">Not listed</option>
                                        @foreach ($type as $code => $option)
                                            <option value="{{ $code }}" @selected((string) $value($field) === (string) $code)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input id="{{ $name }}" name="{{ $name }}"
                                        type="{{ $type === 'money' ? 'number' : $type }}" @if ($type === 'money') step="0.01" min="0" inputmode="decimal" @elseif ($type === 'number') min="0" @endif
                                        value="{{ $type === 'money' ? $dollars($field) : $value($field) }}" class="site-field">
                                @endif
                                @error($field) <p class="font-bold text-[#b42318]">{{ $message }}</p> @enderror
                                @if ($name !== $field) @error($name) <p class="font-bold text-[#b42318]">{{ $message }}</p> @enderror @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($heading === 'Playing' && $has('levels'))
                        <fieldset class="mt-5">
                            <legend class="font-bold">Levels you cater for</legend>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach (Club::LEVELS as $code => $level)
                                    <label class="site-chip has-checked:border-court has-checked:bg-court-soft">
                                        <input type="checkbox" name="levels[]" value="{{ $code }}" class="size-5 accent-court" @checked(in_array($code, old('levels', $listing->levels ?? [])))>
                                        {{ $level }}
                                    </label>
                                @endforeach
                            </div>
                            @error('levels.*') <p class="mt-1 font-bold text-[#b42318]">{{ $message }}</p> @enderror
                        </fieldset>
                    @endif

                    @if ($heading === ($kind === 'club' ? 'Playing' : 'Courts') && $yesNo)
                        <div class="mt-5 grid gap-x-5 gap-y-3 sm:grid-cols-2">
                            @foreach ($yesNo as $field => $label)
                                @php($current = old($field, $listing->getAttribute($field) === null ? '' : (int) $listing->getAttribute($field)))
                                <div class="flex items-center justify-between gap-3">
                                    <label for="{{ $field }}" class="min-w-0 font-bold">{{ $label }}</label>
                                    <select id="{{ $field }}" name="{{ $field }}" class="site-field w-36! shrink-0">
                                        <option value="" @selected($current === '' || $current === null)>Not sure</option>
                                        <option value="1" @selected((string) $current === '1')>Yes</option>
                                        <option value="0" @selected((string) $current === '0')>No</option>
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach

            <div class="flex flex-wrap items-center gap-4">
                <button class="site-btn-primary min-h-14 px-8 text-lg">Save changes</button>
                <a href="{{ $listingUrl }}" class="site-link">Cancel</a>
            </div>
        </form>
    </div>
</x-layout>
