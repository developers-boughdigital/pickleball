@php
    use App\Models\Club;

    $exportQuery = array_filter($filters);
@endphp

<x-admin.layout title="Clubs" :breadcrumb="['Directory', 'Clubs']">
    <x-admin.page-header title="Clubs" subtitle="Who plays where: sessions, levels, fees and how to join.">
        @can('clubs.import')
            <a href="{{ route('admin.import', ['type' => 'clubs']) }}" class="btn-outline">
                <x-icon name="upload" class="size-4" />
                Import
            </a>
        @endcan

        @can('clubs.export')
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = ! open" class="btn-outline">
                    <x-icon name="download" class="size-4" />
                    Export
                    <x-icon name="chevron-down" class="size-3.5 text-slate-500" />
                </button>

                <div x-show="open" x-transition.origin.top.right x-cloak @click.outside="open = false" class="card absolute right-0 z-20 mt-2 w-64 p-1.5">
                    @foreach (['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV (.csv)'] as $format => $label)
                        <a href="{{ route('admin.clubs.export', [...$exportQuery, 'format' => $format]) }}" download @click="open = false" class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm hover:bg-slate-50">
                            <x-icon name="file-spreadsheet" class="size-4 text-emerald-600" />
                            {{ $label }}
                        </a>
                    @endforeach
                    <p class="border-t border-slate-100 px-2.5 pt-1.5 pb-1 text-xs text-slate-500">
                        {{ $exportQuery ? 'Only clubs matching your filters.' : 'All clubs.' }}
                    </p>
                    <a href="{{ route('admin.import.club-venue-export') }}" download @click="open = false" class="flex items-center gap-2 rounded-lg border-t border-slate-100 px-2.5 py-2 text-sm hover:bg-slate-50">
                        <x-icon name="link" class="size-4 text-brand-600" />
                        Club & venue links (.csv)
                    </a>
                </div>
            </div>
        @endcan

        @can('clubs.create')
            <a href="{{ route('admin.clubs.create') }}" class="btn-gold">
                <x-icon name="plus" class="size-4" />
                Add club
            </a>
        @endcan
    </x-admin.page-header>

    <div class="mt-4 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <x-admin.stat label="Clubs" :value="number_format($stats['total'])" :note="'Across '.$stats['states'].' '.str('state')->plural($stats['states'])" />
        <x-admin.stat label="Published" :value="number_format($stats['published'])" :note="'Live in the directory'.($stats['hidden'] ? ' · '.number_format($stats['hidden']).' hidden' : '')" note-class="text-emerald-600" />
        <a href="{{ route('admin.clubs.index', ['record_status' => 'checked', 'status' => 'draft']) }}" class="block rounded-xl transition hover:ring-2 hover:ring-brand-300">
            <x-admin.stat label="Ready to publish" :value="number_format($stats['ready'])" note="Checked, still in draft. Show them" note-class="text-brand-600" />
        </a>
        <x-admin.stat label="No venue yet" :value="number_format($stats['noVenue'])" note="Not linked to where they play" note-class="text-amber-600" />
    </div>

    <form method="GET" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_repeat(3,10rem)]">
        <x-admin.search :value="$filters['search']" placeholder="Search by name, suburb, postcode, email or ref" />
        <x-admin.select name="state" :options="array_combine(Club::STATES, Club::STATES)" :selected="$filters['state']" placeholder="All states" />
        <x-admin.select name="record_status" :options="Club::RECORD_STATUSES" :selected="$filters['record_status']" placeholder="Any record status" />
        <x-admin.select name="status" :options="Club::STATUSES" :selected="$filters['status']" placeholder="Any status" />
    </form>

    <div class="card mt-3 overflow-hidden" x-data="publishing(@js($clubs->pluck('id')))">
        <x-admin.publish-bar type="clubs" noun="club" :paginator="$clubs" :filters="$filters" />
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        @can('clubs.publish')
                            <th class="w-10 py-2.5 pl-4">
                                <input type="checkbox" class="size-4 accent-brand-500" aria-label="Select every club on this page"
                                    :checked="page.length && selected.length === page.length" @change="togglePage($event.target.checked)">
                            </th>
                        @endcan
                        <th class="px-4 py-2.5 font-medium">Club</th>
                        <th class="px-4 py-2.5 font-medium">Location</th>
                        <th class="px-4 py-2.5 font-medium">Venues</th>
                        <th class="px-4 py-2.5 font-medium">Record</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="px-4 py-2.5 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 border-t border-slate-200">
                    @forelse ($clubs as $club)
                        <tr class="transition hover:bg-slate-50/60" :class="(all || selected.includes(@js($club->id))) && 'bg-brand-50/60'">
                            @can('clubs.publish')
                                <td class="py-2.5 pl-4">
                                    <input type="checkbox" form="bulk-clubs" name="ids[]" value="{{ $club->id }}" x-model="selected" class="size-4 accent-brand-500" aria-label="Select {{ $club->name }}">
                                </td>
                            @endcan
                            <td class="max-w-80 px-4 py-2.5">
                                <a href="{{ route('admin.clubs.show', $club) }}" class="font-medium hover:underline">{{ $club->name }}</a>
                                <p class="truncate text-xs text-slate-500">
                                    <span class="font-mono">{{ $club->ref }}</span>
                                    @if ($club->email) · {{ $club->email }} @endif
                                </p>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="text-slate-700">{{ $club->suburb ?? '—' }}</span>
                                <span class="ml-1 text-xs text-slate-500">{{ $club->state }} {{ $club->postcode }}</span>
                            </td>
                            <td class="px-4 py-2.5 font-medium text-ink-950 tabular-nums">{{ $club->venues_count ?: '—' }}</td>
                            <td class="px-4 py-2.5"><span class="{{ $club->recordStatusBadge() }}">{{ $club->label('record_status') }}</span></td>
                            <td class="px-4 py-2.5"><span class="{{ $club->statusBadge() }}">{{ $club->label('status') }}</span></td>
                            <td class="px-4 py-2.5">
                                <div class="flex justify-end gap-1.5">
                                    <x-admin.publish-toggle type="clubs" :listing="$club" />
                                    <a href="{{ route('admin.clubs.show', $club) }}" class="icon-btn-view" title="View {{ $club->name }}">
                                        <x-icon name="eye" class="size-4" />
                                        <span class="sr-only">View {{ $club->name }}</span>
                                    </a>
                                    @can('clubs.update')
                                        <a href="{{ route('admin.clubs.edit', $club) }}" class="icon-btn-edit" title="Edit {{ $club->name }}">
                                            <x-icon name="pencil" class="size-4" />
                                            <span class="sr-only">Edit {{ $club->name }}</span>
                                        </a>
                                    @endcan
                                    @can('clubs.delete')
                                        <form method="POST" action="{{ route('admin.clubs.destroy', $club) }}" @submit="confirm(@js("Delete {$club->name}?")) || $event.preventDefault()">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn-delete" title="Delete {{ $club->name }}">
                                                <x-icon name="trash" class="size-4" />
                                                <span class="sr-only">Delete {{ $club->name }}</span>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->can('clubs.publish') ? 7 : 6 }}" class="px-4 py-8 text-center text-slate-500">
                                @if (array_filter($filters))
                                    No clubs match your filters.
                                @else
                                    No clubs yet. Add one, or import clubs.csv from the directory pack.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$clubs" noun="club" />
    </div>
</x-admin.layout>
