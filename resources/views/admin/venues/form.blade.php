@php
    use App\Models\Venue;

    $editing = $venue->exists;
    $value = fn (string $field) => old($field, $venue->getAttribute($field));
    $date = fn (string $field) => old($field, $venue->getAttribute($field)?->format('Y-m-d'));
    $courtHire = old('court_hire', $venue->court_hire_cents === null ? null : number_format($venue->court_hire_cents / 100, 2, '.', ''));
@endphp

<x-admin.layout :title="$editing ? 'Edit '.$venue->name : 'Add venue'" :breadcrumb="['Venues', $editing ? $venue->name : 'Add venue']">
    <a href="{{ route('admin.venues.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Venues
    </a>

    <form
        method="POST"
        action="{{ $editing ? route('admin.venues.update', $venue) : route('admin.venues.store') }}"
        x-data="{ dirty: false, name: @js($value('name') ?? ''), recordStatus: @js($value('record_status')) }"
        @input="dirty = true"
        @change="dirty = true"
        class="mt-3 space-y-3"
    >
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-admin.form-heading icon="map-pin">
            <h1 class="truncate text-xl font-bold tracking-tight" x-text="name || 'New venue'">{{ $venue->name ?: 'New venue' }}</h1>
            <p class="truncate text-sm text-slate-500">
                @if ($editing)
                    <span class="font-mono">/{{ $venue->slug }}</span> · {{ $venue->fullAddress() }}
                @else
                    New venues start as drafts. Fill in what you know; the rest can wait until it’s checked.
                @endif
            </p>
        </x-admin.form-heading>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Venue</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="sm:col-span-2">
                    <x-admin.input name="name" label="Venue name" icon="map-pin" :value="$value('name')" x-model="name" required maxlength="255" placeholder="e.g. Pickledome" />
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                        <input type="hidden" name="name_needs_confirming" value="0">
                        <input type="checkbox" name="name_needs_confirming" value="1" @checked($value('name_needs_confirming')) class="rounded border-slate-300 accent-amber-500">
                        Name needs confirming (the map only gave the club’s name)
                    </label>
                </div>
                <x-admin.input name="ref" label="Ref" icon="tag" :value="$value('ref')" maxlength="20" class="font-mono" hint="Matches the directory import pack." />
                <x-admin.field-select name="venue_type" label="Venue type" :options="Venue::TYPES" :selected="$value('venue_type')" />
            </div>
        </section>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Location</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="sm:col-span-2">
                    <x-admin.input name="street_address" label="Street address" :value="$value('street_address')" maxlength="255" placeholder="56 Coyne Street" />
                </div>
                <x-admin.input name="suburb" label="Suburb" :value="$value('suburb')" maxlength="100" />
                <div class="grid grid-cols-2 gap-3">
                    <x-admin.field-select name="state" label="State" :options="Venue::STATES" :selected="$value('state')" />
                    <x-admin.input name="postcode" label="Postcode" :value="$value('postcode')" inputmode="numeric" maxlength="4" pattern="\d{4}" />
                </div>
                <x-admin.input name="council" label="Council (LGA)" :value="$value('council')" maxlength="100" />
                <x-admin.input name="city" label="City or region" :value="$value('city')" maxlength="100" />
                <x-admin.input name="latitude" label="Latitude" type="number" step="any" min="-90" max="90" :value="$value('latitude')" placeholder="-35.410446" />
                <x-admin.input name="longitude" label="Longitude" type="number" step="any" min="-180" max="180" :value="$value('longitude')" placeholder="149.129444" />
                <div class="sm:col-span-2">
                    <x-admin.input name="google_place_id" label="Google place ID" :value="$value('google_place_id')" maxlength="255" class="font-mono" placeholder="ChIJ…" />
                </div>
            </div>
        </section>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Courts & facilities</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-admin.input name="court_count" label="Number of courts" type="number" min="0" max="500" :value="$value('court_count')" />
                <x-admin.field-select name="indoor_outdoor" label="Indoor/outdoor" :options="Venue::SETTINGS" :selected="$value('indoor_outdoor')" />
                <x-admin.field-select name="surface" label="Surface" :options="Venue::SURFACES" :selected="$value('surface')" />
                <x-admin.field-select name="court_lines" label="Court lines" :options="Venue::COURT_LINES" :selected="$value('court_lines')" />
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                @foreach (Venue::FACILITIES as $key => $label)
                    <x-admin.yes-no :name="$key" :label="$label" :value="old($key, $venue->getAttribute($key))" />
                @endforeach
            </div>
        </section>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Booking & contact</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <x-admin.input name="court_hire" label="Court hire (AUD per hour)" type="number" step="0.01" min="0" :value="$courtHire" placeholder="13.00" />
                    @error('court_hire_cents') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <x-admin.input name="phone" label="Phone" icon="phone" :value="$value('phone')" maxlength="30" placeholder="02 6000 0000" />
                <div class="sm:col-span-2">
                    <x-admin.input name="price_notes" label="Price notes" :value="$value('price_notes')" maxlength="1000" placeholder="Off-peak $15 before 4pm" />
                </div>
                <div class="sm:col-span-2">
                    <x-admin.input name="booking_url" label="Booking link" type="url" icon="globe" :value="$value('booking_url')" maxlength="255" placeholder="https://" />
                </div>
                <div class="sm:col-span-2">
                    <x-admin.input name="website" label="Website" type="url" icon="globe" :value="$value('website')" maxlength="255" placeholder="https://" />
                </div>
                <div class="sm:col-span-2 xl:col-span-4">
                    <x-admin.input name="opening_hours_text" label="Opening hours" icon="clock" :value="$value('opening_hours_text')" maxlength="1000" placeholder="Mon–Fri 7am–10pm; Sat–Sun 8am–6pm" />
                </div>
            </div>
        </section>

        <x-admin.seo-fields :listing="$venue" :default-title="($venue->name ?: 'Venue name').' pickleball courts'.($venue->suburb ? ', '.$venue->suburb : '')"
            :url="$editing ? route('explore.venue', $venue) : url('explore/venues/…')" />

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Verification & publishing</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-admin.field-select name="record_status" label="Record status" :options="Venue::RECORD_STATUSES" :selected="$value('record_status')" :placeholder="false" x-model="recordStatus" />
                <div>
                    <x-admin.field-select name="status" label="Status" :options="Venue::STATUSES" :selected="$value('status')" :placeholder="false" />
                    <p x-show="recordStatus !== 'checked'" class="mt-1 text-xs text-slate-500">Can be published once the record status is Checked.</p>
                </div>
                <x-admin.field-select name="claim_status" label="Claim status" :options="Venue::CLAIM_STATUSES" :selected="$value('claim_status')" :placeholder="false" />
                <x-admin.input name="profile_tier" label="Profile tier" type="number" min="1" max="9" :value="$value('profile_tier')" hint="1 basic, 2 enriched from the website." />
                <x-admin.input name="source_type" label="Source type" :value="$value('source_type')" maxlength="255" placeholder="Own website" />
                <x-admin.input name="source_url" label="Source URL" icon="globe" :value="$value('source_url')" maxlength="500" placeholder="https:// or where it came from" />
                <div class="grid grid-cols-2 gap-3">
                    <x-admin.input name="checked_at" label="Date checked" type="date" :value="$date('checked_at')" />
                    <x-admin.input name="enriched_at" label="Date enriched" type="date" :value="$date('enriched_at')" />
                </div>
                <x-admin.input name="checked_by" label="Checked by" :value="$value('checked_by')" maxlength="100" />
                <div class="sm:col-span-2 xl:col-span-4">
                    <label for="notes" class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="5000" class="field h-auto w-full py-2">{{ $value('notes') }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <x-admin.form-actions>
            <a href="{{ $editing ? route('admin.venues.show', $venue) : route('admin.venues.index') }}" class="btn-outline">Cancel</a>
            <button class="btn-gold">
                <x-icon name="check" class="size-4" />
                {{ $editing ? 'Save changes' : 'Add venue' }}
            </button>
        </x-admin.form-actions>
    </form>
</x-admin.layout>
