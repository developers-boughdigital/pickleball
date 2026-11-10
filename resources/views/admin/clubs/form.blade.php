@php
    use App\Models\Club;

    $editing = $club->exists;
    $value = fn (string $field) => old($field, $club->getAttribute($field));
    $date = fn (string $field) => old($field, $club->getAttribute($field)?->format('Y-m-d'));
    $dollars = fn (string $field, string $column) => old($field, $club->getAttribute($column) === null ? null : number_format($club->getAttribute($column) / 100, 2, '.', ''));
    $levels = old('levels', $club->levels ?? []);

    $venues = $club->relationLoaded('venues') ? $club->venues : collect();
    $mainVenue = old('main_venue', $venues->firstWhere('pivot.is_primary', true)?->ref);
    $otherVenues = old('other_venues_text', $venues->where('pivot.is_primary', false)->pluck('ref')->sort()->implode(', '));
    $venueError = $errors->first('main_venue') ?: collect($errors->getMessages())->first(fn ($messages, $key) => str_starts_with($key, 'other_venues'))[0] ?? null;
@endphp

<x-admin.layout :title="$editing ? 'Edit '.$club->name : 'Add club'" :breadcrumb="['Clubs', $editing ? $club->name : 'Add club']">
    <a href="{{ route('admin.clubs.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Clubs
    </a>

    <form
        method="POST"
        action="{{ $editing ? route('admin.clubs.update', $club) : route('admin.clubs.store') }}"
        x-data="{ dirty: false, name: @js($value('name') ?? ''), recordStatus: @js($value('record_status')) }"
        @input="dirty = true"
        @change="dirty = true"
        class="mt-3 space-y-3"
    >
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-admin.form-heading icon="users">
            <h1 class="truncate text-xl font-bold tracking-tight" x-text="name || 'New club'">{{ $club->name ?: 'New club' }}</h1>
            <p class="truncate text-sm text-slate-500">
                @if ($editing)
                    <span class="font-mono">/{{ $club->slug }}</span> · {{ $club->location() }}
                @else
                    New clubs start as drafts. Fill in what you know; the rest can wait until it’s checked.
                @endif
            </p>
        </x-admin.form-heading>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Club</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="sm:col-span-2">
                    <x-admin.input name="name" label="Club name" icon="users" :value="$value('name')" x-model="name" required maxlength="255" placeholder="e.g. Pickleball ACT" />
                </div>
                <x-admin.input name="ref" label="Ref" icon="tag" :value="$value('ref')" maxlength="20" class="font-mono" hint="Matches the directory import pack." />
                <x-admin.input name="state_affiliation" label="State affiliation" :value="$value('state_affiliation')" maxlength="100" placeholder="Pickleball Victoria" />
                <div class="sm:col-span-2 xl:col-span-4">
                    <x-admin.input name="short_description" label="Short description" :value="$value('short_description')" maxlength="500" placeholder="One or two sentences for the directory listing" />
                </div>
                <div class="sm:col-span-2 xl:col-span-4">
                    <label for="description" class="mb-1 block text-sm font-medium">Description</label>
                    <textarea id="description" name="description" rows="3" maxlength="10000" class="field h-auto w-full py-2">{{ $value('description') }}</textarea>
                    @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Location & venues</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-admin.input name="suburb" label="Suburb" :value="$value('suburb')" maxlength="100" />
                <div class="grid grid-cols-2 gap-3">
                    <x-admin.field-select name="state" label="State" :options="Club::STATES" :selected="$value('state')" />
                    <x-admin.input name="postcode" label="Postcode" :value="$value('postcode')" inputmode="numeric" maxlength="4" pattern="\d{4}" />
                </div>
                <x-admin.input name="council" label="Council (LGA)" :value="$value('council')" maxlength="100" />
                <x-admin.input name="city" label="City or region" :value="$value('city')" maxlength="100" />
                <x-admin.input name="main_venue" label="Main venue" icon="map-pin" :value="$mainVenue" maxlength="20" class="font-mono uppercase" placeholder="V0001" />
                <div class="sm:col-span-2 xl:col-span-3">
                    <x-admin.input name="other_venues_text" label="Other venues" icon="map-pin" :value="$otherVenues" class="font-mono uppercase" placeholder="V0002, V0003" hint="Venue refs, separated by commas." />
                </div>
                @if ($venueError)
                    <p class="flex items-center gap-1 text-xs text-red-600 sm:col-span-2 xl:col-span-4"><x-icon name="triangle-alert" class="size-3.5" /> {{ $venueError }}</p>
                @endif
            </div>
        </section>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Play</h2>
            <div class="space-y-4">
                <fieldset>
                    <legend class="mb-1.5 text-sm font-medium">Levels</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (Club::LEVELS as $level => $label)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm has-checked:border-brand-300 has-checked:bg-brand-50">
                                <input type="checkbox" name="levels[]" value="{{ $level }}" @checked(in_array($level, $levels)) class="rounded border-slate-300 accent-emerald-600">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('levels.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </fieldset>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.input name="session_types_text" label="Session types" :value="old('session_types_text', implode(', ', $club->session_types ?? []))" placeholder="social, competitive, womens" hint="Separated by commas." />
                    <x-admin.input name="leagues_text" label="Leagues" :value="$value('leagues_text')" maxlength="2000" placeholder="DUPR leagues, tournaments" />
                    <div class="sm:col-span-2">
                        <label for="session_times_text" class="mb-1 block text-sm font-medium">Session times</label>
                        <textarea id="session_times_text" name="session_times_text" rows="2" maxlength="2000" class="field h-auto w-full py-2" placeholder="Tue 7–9pm all levels; Sat 9–11am beginners">{{ $value('session_times_text') }}</textarea>
                        @error('session_times_text') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <x-admin.input name="coach_names_text" label="Coaches" :value="$value('coach_names_text')" maxlength="2000" />
                    </div>
                </div>
                @error('session_types.*') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach (Club::FEATURES as $key => $label)
                        <x-admin.yes-no :name="$key" :label="$label" :value="old($key, $club->getAttribute($key))" />
                    @endforeach
                </div>
            </div>
        </section>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Fees & booking</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <x-admin.input name="membership_fee" label="Membership (AUD per year)" type="number" step="0.01" min="0" :value="$dollars('membership_fee', 'membership_fee_cents')" placeholder="80.00" />
                    @error('membership_fee_cents') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-admin.input name="casual_fee" label="Casual (AUD per session)" type="number" step="0.01" min="0" :value="$dollars('casual_fee', 'casual_fee_cents')" placeholder="10.00" />
                    @error('casual_fee_cents') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-admin.input name="court_hire" label="Court hire (AUD per hour)" type="number" step="0.01" min="0" :value="$dollars('court_hire', 'court_hire_cents')" placeholder="30.00" />
                    @error('court_hire_cents') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <x-admin.field-select name="booking_method" label="Booking method" :options="Club::BOOKING_METHODS" :selected="$value('booking_method')" />
                <div class="sm:col-span-2">
                    <x-admin.input name="booking_url" label="Booking link" type="url" icon="globe" :value="$value('booking_url')" maxlength="255" placeholder="https://" />
                </div>
                <div class="sm:col-span-2">
                    <x-admin.input name="calendar_url" label="Calendar link" type="url" icon="calendar" :value="$value('calendar_url')" maxlength="255" placeholder="https://" />
                </div>
                <div class="sm:col-span-2 xl:col-span-4">
                    <x-admin.input name="membership_notes" label="Membership notes" :value="$value('membership_notes')" maxlength="2000" placeholder="3 visits before joining; juniors half price" />
                </div>
            </div>
        </section>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Contact & about</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="sm:col-span-2">
                    <x-admin.input name="email" label="Email" type="email" icon="mail" :value="$value('email')" maxlength="255" />
                </div>
                <x-admin.input name="phone" label="Phone" icon="phone" :value="$value('phone')" maxlength="30" />
                <div class="grid grid-cols-2 gap-3">
                    <x-admin.input name="year_established" label="Founded" type="number" min="1900" max="2100" :value="$value('year_established')" />
                    <x-admin.input name="member_count" label="Members" type="number" min="0" :value="$value('member_count')" />
                </div>
                @foreach (Club::SOCIALS as $key => $label)
                    <div class="sm:col-span-2">
                        <x-admin.input :name="$key" :label="$label" type="url" icon="globe" :value="$value($key)" maxlength="255" placeholder="https://" />
                    </div>
                @endforeach
            </div>
        </section>

        <x-admin.seo-fields :listing="$club" :default-title="($club->name ?: 'Club name').($club->suburb ? ', '.$club->suburb : '')"
            :url="$editing ? route('explore.club', $club) : url('explore/clubs/…')" />

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Verification & publishing</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-admin.field-select name="record_status" label="Record status" :options="Club::RECORD_STATUSES" :selected="$value('record_status')" :placeholder="false" x-model="recordStatus" />
                <div>
                    <x-admin.field-select name="status" label="Status" :options="Club::STATUSES" :selected="$value('status')" :placeholder="false" />
                    <p x-show="recordStatus !== 'checked'" class="mt-1 text-xs text-slate-500">Can be published once the record status is Checked.</p>
                </div>
                <x-admin.field-select name="claim_status" label="Claim status" :options="Club::CLAIM_STATUSES" :selected="$value('claim_status')" :placeholder="false" />
                <x-admin.input name="profile_tier" label="Profile tier" type="number" min="1" max="9" :value="$value('profile_tier')" hint="1 basic, 2 enriched from the website." />
                <x-admin.input name="source_type" label="Source type" :value="$value('source_type')" maxlength="255" placeholder="Own website" />
                <x-admin.input name="source_url" label="Source URL" icon="globe" :value="$value('source_url')" maxlength="500" placeholder="https:// or where it came from" />
                <div class="grid grid-cols-2 gap-3">
                    <x-admin.input name="checked_at" label="Date checked" type="date" :value="$date('checked_at')" />
                    <x-admin.input name="enriched_at" label="Date enriched" type="date" :value="$date('enriched_at')" />
                </div>
                <x-admin.input name="checked_by" label="Checked by" :value="$value('checked_by')" maxlength="100" />
                <div class="sm:col-span-2">
                    <label for="enrichment_evidence" class="mb-1 block text-sm font-medium">Enrichment evidence <span class="text-xs font-normal text-slate-400">Admin only</span></label>
                    <textarea id="enrichment_evidence" name="enrichment_evidence" rows="3" maxlength="10000" class="field h-auto w-full py-2">{{ $value('enrichment_evidence') }}</textarea>
                    @error('enrichment_evidence') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="notes" class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="5000" class="field h-auto w-full py-2">{{ $value('notes') }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <x-admin.form-actions>
            <a href="{{ $editing ? route('admin.clubs.show', $club) : route('admin.clubs.index') }}" class="btn-outline">Cancel</a>
            <button class="btn-gold">
                <x-icon name="check" class="size-4" />
                {{ $editing ? 'Save changes' : 'Add club' }}
            </button>
        </x-admin.form-actions>
    </form>
</x-admin.layout>
