@php
    use App\Models\Venue;

    $user = auth()->user();
    $exportQuery = array_filter($filters);
@endphp

<x-admin.layout title="Venues" :breadcrumb="['Directory', 'Venues']">
    <x-admin.page-header title="Venues" subtitle="Where people can play: courts, facilities and how to book.">
        @can('venues.import')
            <a href="{{ route('admin.import', ['type' => 'venues']) }}" class="btn-outline">
                <x-icon name="upload" class="size-4" />
                Import
            </a>
        @endcan

        @can('venues.export')
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = ! open" class="btn-outline">
                    <x-icon name="download" class="size-4" />
                    Export
                    <x-icon name="chevron-down" class="size-3.5 text-slate-500" />
                </button>

                <div x-show="open" x-transition.origin.top.right x-cloak @click.outside="open = false" class="card absolute right-0 z-20 mt-2 w-60 p-1.5">
                    @foreach (['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV (.csv)'] as $format => $label)
                        <a href="{{ route('admin.venues.export', [...$exportQuery, 'format' => $format]) }}" download @click="open = false" class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm hover:bg-slate-50">
                            <x-icon name="file-spreadsheet" class="size-4 text-emerald-600" />
                            {{ $label }}
                        </a>
                    @endforeach
                    <p class="border-t border-slate-100 px-2.5 pt-1.5 pb-1 text-xs text-slate-500">
                        {{ $exportQuery ? 'Only venues matching your filters.' : 'All venues.' }}
                    </p>
                </div>
            </div>
        @endcan

        @can('venues.create')
            <a href="{{ route('admin.venues.create') }}" class="btn-gold">
                <x-icon name="plus" class="size-4" />
                Add venue
            </a>
        @endcan
    </x-admin.page-header>

    <div class="mt-4 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <x-admin.stat label="Venues" :value="number_format($stats['total'])" :note="'Across '.$stats['states'].' '.str('state')->plural($stats['states'])" />
        <x-admin.stat label="Published" :value="number_format($stats['published'])" note="Live in the directory" note-class="text-emerald-600" />
        <x-admin.stat label="Ready to publish" :value="number_format($stats['ready'])" note="Checked, still in draft" note-class="text-brand-600" />
        <x-admin.stat label="Name to confirm" :value="number_format($stats['confirmName'])" note="Map only gave the club’s name" note-class="text-amber-600" />
    </div>

    <form method="GET" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_repeat(4,10rem)]">
        <x-admin.search :value="$filters['search']" placeholder="Search by name, suburb, postcode or ref" />
        <x-admin.select name="state" :options="array_combine(Venue::STATES, Venue::STATES)" :selected="$filters['state']" placeholder="All states" />
        <x-admin.select name="setting" :options="Venue::SETTINGS" :selected="$filters['setting']" placeholder="Indoor & outdoor" />
        <x-admin.select name="record_status" :options="Venue::RECORD_STATUSES" :selected="$filters['record_status']" placeholder="Any record status" />
        <x-admin.select name="status" :options="Venue::STATUSES" :selected="$filters['status']" placeholder="Draft & published" />
    </form>

    <div class="card mt-3 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Venue</th>
                        <th class="px-4 py-2.5 font-medium">Location</th>
                        <th class="px-4 py-2.5 font-medium">Courts</th>
                        <th class="px-4 py-2.5 font-medium">Record</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="px-4 py-2.5 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 border-t border-slate-200">
                    @forelse ($venues as $venue)
                        <tr class="transition hover:bg-slate-50/60">
                            <td class="max-w-80 px-4 py-2.5">
                                <a href="{{ route('admin.venues.show', $venue) }}" class="font-medium hover:underline">{{ $venue->name }}</a>
                                @if ($venue->name_needs_confirming)
                                    <span class="badge-pending ml-1" title="The map only gave the club’s name for this venue">Name to confirm</span>
                                @endif
                                <p class="truncate text-xs text-slate-500">
                                    <span class="font-mono">{{ $venue->ref }}</span>
                                    @if ($venue->clubs_count) · {{ $venue->clubs_count }} {{ str('club')->plural($venue->clubs_count) }} @endif
                                    @if ($venue->street_address) · {{ $venue->street_address }} @endif
                                </p>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="text-slate-700">{{ $venue->suburb ?? '—' }}</span>
                                <span class="ml-1 text-xs text-slate-500">{{ $venue->state }} {{ $venue->postcode }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-600">
                                <span class="font-medium text-ink-950 tabular-nums">{{ $venue->court_count ?? '—' }}</span>
                                @if ($venue->indoor_outdoor)
                                    <span class="ml-1 text-xs text-slate-500">{{ $venue->label('indoor_outdoor') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5"><span class="{{ $venue->recordStatusBadge() }}">{{ $venue->label('record_status') }}</span></td>
                            <td class="px-4 py-2.5"><span class="{{ $venue->statusBadge() }}">{{ $venue->label('status') }}</span></td>
                            <td class="px-4 py-2.5">
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('admin.venues.show', $venue) }}" class="icon-btn-view" title="View {{ $venue->name }}">
                                        <x-icon name="eye" class="size-4" />
                                        <span class="sr-only">View {{ $venue->name }}</span>
                                    </a>
                                    @can('venues.update')
                                        <a href="{{ route('admin.venues.edit', $venue) }}" class="icon-btn-edit" title="Edit {{ $venue->name }}">
                                            <x-icon name="pencil" class="size-4" />
                                            <span class="sr-only">Edit {{ $venue->name }}</span>
                                        </a>
                                    @endcan
                                    @can('venues.delete')
                                        <form method="POST" action="{{ route('admin.venues.destroy', $venue) }}" @submit="confirm(@js("Delete {$venue->name}?")) || $event.preventDefault()">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn-delete" title="Delete {{ $venue->name }}">
                                                <x-icon name="trash" class="size-4" />
                                                <span class="sr-only">Delete {{ $venue->name }}</span>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                                @if (array_filter($filters))
                                    No venues match your filters.
                                @else
                                    No venues yet. Add one, or import venues.csv from the directory pack.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$venues" noun="venue" />
    </div>
</x-admin.layout>
