@php
    use App\Models\Venue;

    $yesNo = fn (?bool $value) => $value === null ? 'Unknown' : ($value ? 'Yes' : 'No');
    $details = fn (array $rows) => array_filter($rows, fn ($value) => filled($value));
@endphp

<x-admin.layout :title="$venue->name" :breadcrumb="['Venues', $venue->name]">
    <a href="{{ route('admin.venues.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Venues
    </a>

    <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <x-admin.form-heading icon="map-pin">
            <h1 class="text-xl font-bold tracking-tight">{{ $venue->name }}</h1>
            <p class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <span class="font-mono">{{ $venue->code }}</span>
                <span class="{{ $venue->statusBadge() }}">{{ $venue->status }}</span>
                @if ($venue->venue_type) <span>{{ $venue->venue_type }}</span> @endif
            </p>
        </x-admin.form-heading>

        <div class="flex items-center gap-2">
            @can('venues.delete')
                <form method="POST" action="{{ route('admin.venues.destroy', $venue) }}" @submit="confirm(@js("Delete {$venue->name}?")) || $event.preventDefault()">
                    @csrf
                    @method('DELETE')
                    <button class="btn-outline text-red-600 hover:bg-red-50">
                        <x-icon name="trash" class="size-4" />
                        Delete
                    </button>
                </form>
            @endcan
            @can('venues.update')
                <a href="{{ route('admin.venues.edit', $venue) }}" class="btn-gold">
                    <x-icon name="pencil" class="size-4" />
                    Edit
                </a>
            @endcan
        </div>
    </div>

    <div class="mt-4 grid gap-3 xl:grid-cols-3">
        <div class="space-y-3 xl:col-span-2">
            <section class="card p-4 sm:p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold">Location</h2>
                    @if ($venue->mapUrl())
                        <a href="{{ $venue->mapUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline">
                            Open in Google Maps <x-icon name="external-link" class="size-3.5" />
                        </a>
                    @endif
                </div>
                <p class="text-sm">{{ $venue->fullAddress() ?: 'No address yet.' }}</p>
                <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    @foreach ($details([
                        'Council (LGA)' => $venue->council,
                        'City or region' => $venue->city,
                        'Coordinates' => $venue->latitude !== null ? "{$venue->latitude}, {$venue->longitude}" : null,
                        'Google place ID' => $venue->google_place_id,
                    ]) as $label => $text)
                        <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="truncate text-right">{{ $text }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="card p-4 sm:p-5">
                <h2 class="mb-3 text-sm font-semibold">Courts & facilities</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach (['Courts' => $venue->court_count, 'Indoor/outdoor' => $venue->indoor_outdoor, 'Surface' => $venue->surface, 'Court lines' => $venue->court_lines] as $label => $text)
                        <div class="rounded-lg bg-slate-50 p-3">
                            <p class="text-xs text-slate-500">{{ $label }}</p>
                            <p class="mt-0.5 text-sm font-semibold">{{ $text ?? '—' }}</p>
                        </div>
                    @endforeach
                </div>

                <ul class="mt-3 flex flex-wrap gap-1.5">
                    @foreach (['lights' => 'Lights', ...Venue::FACILITIES] as $key => $label)
                        @php($has = $key === 'lights' ? $venue->lights : $venue->facility($key))
                        <li @class([
                            'inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium ring-1',
                            'bg-emerald-50 text-emerald-700 ring-emerald-200' => $has === true,
                            'bg-red-50 text-red-600 ring-red-200 line-through' => $has === false,
                            'bg-white text-slate-500 ring-slate-200' => $has === null,
                        ]) title="{{ $label }}: {{ $yesNo($has) }}">
                            @if ($has === true) <x-icon name="check" class="size-3" /> @endif
                            {{ $label }}{{ $has === null ? '?' : '' }}
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="card p-4 sm:p-5">
                <h2 class="mb-3 text-sm font-semibold">Booking & contact</h2>
                <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    @php($links = ['Website' => $venue->website, 'Booking link' => $venue->booking_url])
                    @forelse ($details([
                        'Price per court hour' => $venue->price_per_hour !== null ? '$'.number_format($venue->price_per_hour, 2) : null,
                        'Price notes' => $venue->price_notes,
                        'Opening hours' => $venue->opening_hours,
                        'Phone' => $venue->phone,
                        ...$links,
                        'Clubs that play here' => implode(', ', $venue->clubCodes()),
                    ]) as $label => $text)
                        <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                            <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                            <dd class="min-w-0 truncate text-right">
                                @if (isset($links[$label]))
                                    <a href="{{ $text }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">{{ preg_replace('~^https?://(www\.)?~', '', rtrim($text, '/')) }}</a>
                                @elseif ($label === 'Phone')
                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $text) }}" class="hover:underline">{{ $text }}</a>
                                @else
                                    {{ $text }}
                                @endif
                            </dd>
                        </div>
                    @empty
                        <p class="text-slate-500">No booking or contact details yet.</p>
                    @endforelse
                </dl>
            </section>
        </div>

        <section class="card self-start p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">Verification</h2>
            <dl class="space-y-2 text-sm">
                @foreach ([
                    'Source' => $venue->source_type,
                    'Date checked' => $venue->checked_at?->format('j M Y'),
                    'Checked by' => $venue->checked_by,
                    'Last updated' => $venue->updated_at?->diffForHumans(),
                ] as $label => $text)
                    <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                        <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                        <dd class="text-right">{{ $text ?? '—' }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($venue->source_url)
                <a href="{{ $venue->source_url }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline">
                    View source <x-icon name="external-link" class="size-3.5" />
                </a>
            @endif
            @if ($venue->notes)
                <p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs whitespace-pre-line text-amber-900">{{ $venue->notes }}</p>
            @endif
        </section>
    </div>
</x-admin.layout>
