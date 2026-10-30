@php
    use App\Models\Venue;

    $editing = $venue->exists;
    $value = fn (string $field) => old($field, $venue->getAttribute($field));
@endphp

<x-admin.layout :title="$editing ? 'Edit '.$venue->name : 'Add venue'" :breadcrumb="['Venues', $editing ? $venue->name : 'Add venue']">
    <a href="{{ route('admin.venues.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Venues
    </a>

    <form
        method="POST"
        action="{{ $editing ? route('admin.venues.update', $venue) : route('admin.venues.store') }}"
        x-data="{ dirty: false, name: @js($value('name') ?? '') }"
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
            <p class="truncate text-sm text-slate-500">{{ $editing ? $venue->fullAddress() : 'Fill in what you know; the rest can wait until it’s checked.' }}</p>
        </x-admin.form-heading>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Venue</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="sm:col-span-2">
                    <x-admin.input name="name" label="Venue name" icon="map-pin" :value="$value('name')" x-model="name" required maxlength="255" placeholder="e.g. Pickledome" />
                </div>
                <x-admin.input name="code" label="Venue ID" icon="tag" :value="$value('code')" maxlength="20" class="font-mono" hint="Matches the directory spreadsheet." />
                <x-admin.field-select name="status" label="Status" :options="Venue::STATUSES" :selected="$value('status')" :placeholder="false" />
                <x-admin.field-select name="venue_type" label="Venue type" :options="Venue::TYPES" :selected="$value('venue_type')" />
            </div>
        </section>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Location</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="sm:col-span-2">
                    <x-admin.input name="address" label="Street address" :value="$value('address')" maxlength="255" placeholder="56 Coyne Street" />
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
                <x-admin.yes-no name="lights" label="Lights" :value="old('lights', $venue->lights)" />
                @foreach (Venue::FACILITIES as $key => $label)
                    <x-admin.yes-no :name="'facilities['.$key.']'" :label="$label" :value="old('facilities.'.$key, $venue->facility($key))" />
                @endforeach
            </div>
        </section>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Booking & contact</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-admin.input name="price_per_hour" label="Price (AUD per court hour)" type="number" step="0.01" min="0" :value="$value('price_per_hour')" placeholder="20" />
                <x-admin.input name="opening_hours" label="Opening hours" icon="clock" :value="$value('opening_hours')" maxlength="255" placeholder="Mon–Fri 7am–10pm" />
                <div class="sm:col-span-2">
                    <x-admin.input name="price_notes" label="Price notes" :value="$value('price_notes')" maxlength="1000" placeholder="Off-peak $15 before 4pm" />
                </div>
                <div class="sm:col-span-2">
                    <x-admin.input name="booking_url" label="Booking link" type="url" icon="globe" :value="$value('booking_url')" maxlength="255" placeholder="https://" />
                </div>
                <div class="sm:col-span-2">
                    <x-admin.input name="website" label="Website" type="url" icon="globe" :value="$value('website')" maxlength="255" placeholder="https://" />
                </div>
                <x-admin.input name="phone" label="Phone" icon="phone" :value="$value('phone')" maxlength="30" placeholder="02 6000 0000" />
                <x-admin.input name="club_codes" label="Clubs that play here" :value="old('club_codes', implode(', ', $venue->clubCodes()))" maxlength="255" class="font-mono" placeholder="C0001, C0002" />
            </div>
        </section>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Verification</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-admin.field-select name="source_type" label="Source type" :options="Venue::SOURCE_TYPES" :selected="$value('source_type')" />
                <x-admin.input name="source_url" label="Source URL" type="url" icon="globe" :value="$value('source_url')" maxlength="255" placeholder="https://" />
                <x-admin.input name="checked_at" label="Date checked" type="date" :value="old('checked_at', $venue->checked_at?->format('Y-m-d'))" />
                <x-admin.input name="checked_by" label="Checked by" :value="$value('checked_by')" maxlength="100" placeholder="Initials" />
                <div class="sm:col-span-2 xl:col-span-4">
                    <label for="notes" class="mb-1 block text-sm font-medium">Notes</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="2000" class="field h-auto w-full py-2">{{ $value('notes') }}</textarea>
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
