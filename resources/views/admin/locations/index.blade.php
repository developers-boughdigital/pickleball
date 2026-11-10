@php
    $percent = fn (int $part, int $total) => $total ? round($part / $total * 100) : 0;
    $venues = $coverage['venues'];
    $clubs = $coverage['clubs'];
@endphp

<x-admin.layout title="Locations" :breadcrumb="['Directory', 'Locations']">
    <x-admin.page-header title="Locations" subtitle="Every venue and club needs a state, city or region, council and suburb to appear on its location pages.">
        <a href="{{ route('play') }}" target="_blank" class="btn-outline" data-reload>
            <x-icon name="external-link" class="size-4" />
            Location pages
        </a>
        @can('venues.update')
            <form method="POST" action="{{ route('admin.locations.fill') }}">
                @csrf
                <button class="btn-gold" @disabled(! $localities)>
                    <x-icon name="map-pin" class="size-4" />
                    Fill from postcodes
                </button>
            </form>
        @endcan
    </x-admin.page-header>

    <div class="mt-4 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <x-admin.stat label="Venues with all four levels" :value="$percent($venues['complete'], $venues['total']).'%'" :note="number_format($venues['complete']).' of '.number_format($venues['total'])" :note-class="$percent($venues['complete'], $venues['total']) >= 95 ? 'text-emerald-600' : 'text-amber-600'" />
        <x-admin.stat label="Clubs with all four levels" :value="$percent($clubs['complete'], $clubs['total']).'%'" :note="number_format($clubs['complete']).' of '.number_format($clubs['total'])" :note-class="$percent($clubs['complete'], $clubs['total']) >= 95 ? 'text-emerald-600' : 'text-amber-600'" />
        <x-admin.stat label="Suburb lookup" :value="number_format($localities)" :note="$localities ? 'Suburbs with council and city' : 'Empty: run php artisan localities:import'" :note-class="$localities ? 'text-brand-600' : 'text-red-600'" />
        <x-admin.stat label="Unknown postcodes" :value="$unknownPostcodes->count()" note="Probably typos; fix them on the listing" :note-class="$unknownPostcodes->isEmpty() ? 'text-emerald-600' : 'text-amber-600'" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
        <section class="card min-w-0 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 p-4 pb-0 sm:px-5">
                <h2 class="text-base font-semibold">Needs a fix</h2>
                <form method="GET" class="flex flex-wrap gap-2">
                    <x-admin.select name="type" :options="['venues' => 'Venues', 'clubs' => 'Clubs']" :selected="$type" />
                    <x-admin.select name="missing" :options="['city' => 'No city or region', 'council' => 'No council', 'postcode' => 'No postcode']" :selected="$missing" placeholder="Any level missing" />
                </form>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[600px] text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">{{ $type === 'clubs' ? 'Club' : 'Venue' }}</th>
                            <th class="px-4 py-2.5 font-medium">Suburb</th>
                            <th class="px-4 py-2.5 font-medium">City or region</th>
                            <th class="px-4 py-2.5 font-medium">Council</th>
                            <th class="px-4 py-2.5 text-right font-medium">Fix</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 border-t border-slate-200">
                        @forelse ($needsFix as $listing)
                            <tr class="transition hover:bg-slate-50/60">
                                <td class="max-w-72 px-4 py-2.5">
                                    <p class="truncate font-medium">{{ $listing->name }}</p>
                                    <p class="font-mono text-xs text-slate-500">{{ $listing->ref }}</p>
                                </td>
                                <td class="px-4 py-2.5">
                                    {{ $listing->suburb ?? '—' }}
                                    <span @class(['ml-1 text-xs', 'font-medium text-amber-700' => $listing->postcode && $unknownPostcodes->contains($listing->postcode), 'text-slate-500' => ! ($listing->postcode && $unknownPostcodes->contains($listing->postcode))])>{{ $listing->state }} {{ $listing->postcode ?? 'no postcode' }}</span>
                                </td>
                                <td @class(['px-4 py-2.5', 'text-amber-700' => ! $listing->city])>{{ $listing->city ?? 'Missing' }}</td>
                                <td @class(['px-4 py-2.5', 'text-amber-700' => ! $listing->council])>{{ $listing->council ?? 'Missing' }}</td>
                                <td class="px-4 py-2.5">
                                    <div class="flex justify-end">
                                        @can($type.'.update')
                                            <a href="{{ route("admin.{$type}.edit", $listing) }}" class="icon-btn-edit" title="Edit {{ $listing->name }}">
                                                <x-icon name="pencil" class="size-4" />
                                                <span class="sr-only">Edit {{ $listing->name }}</span>
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">Every {{ str($type)->singular() }} has all four location levels.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-admin.pagination :paginator="$needsFix" :noun="str($type)->singular()->value()" />
        </section>

        <div class="space-y-3">
            <section class="card min-w-0 p-4 sm:p-5">
                <h2 class="text-base font-semibold">Levels filled</h2>
                <dl class="mt-3 space-y-2.5 text-sm">
                    @foreach (['state' => 'State', 'city' => 'City or region', 'council' => 'Council', 'suburb' => 'Suburb'] as $key => $label)
                        <div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $label }}</dt><dd class="font-medium tabular-nums">{{ $percent($venues[$key], $venues['total']) }}% of venues</dd></div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ $percent($venues[$key], $venues['total']) }}%"></div></div>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="card min-w-0 p-4 sm:p-5">
                <h2 class="text-base font-semibold">Biggest cities and regions</h2>
                <ul class="mt-3 divide-y divide-slate-100 text-sm">
                    @forelse ($cities as $row)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <a href="{{ $row->url }}" target="_blank" data-reload class="font-medium hover:underline">{{ $row->city }} <span class="text-xs text-slate-500">{{ $row->state }}</span></a>
                            <span class="text-slate-500 tabular-nums">{{ $row->total }} {{ str('venue')->plural($row->total) }}</span>
                        </li>
                    @empty
                        <li class="py-4 text-center text-slate-500">No cities yet. Fill them from postcodes.</li>
                    @endforelse
                </ul>
            </section>

            @if ($unknownPostcodes->isNotEmpty())
                <section class="card min-w-0 p-4 sm:p-5">
                    <h2 class="text-base font-semibold">Postcodes not in the lookup</h2>
                    <p class="mt-1 text-xs text-slate-500">Usually swapped digits. Correct the postcode and the city and council fill in when you save.</p>
                    <p class="mt-2 font-mono text-sm">{{ $unknownPostcodes->join(', ') }}</p>
                </section>
            @endif
        </div>
    </div>
</x-admin.layout>
