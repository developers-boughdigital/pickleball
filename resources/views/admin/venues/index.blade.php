@php
    $user = auth()->user();
    $exportQuery = array_filter($filters);
@endphp

<x-admin.layout title="Venues" :breadcrumb="['Directory', 'Venues']">
    <x-admin.page-header title="Venues" subtitle="Where people can play: courts, facilities and how to book.">
        @can('venues.import')
            <div x-data="{ open: @js($errors->has('file')) }" class="relative">
                <button type="button" @click="open = ! open" class="btn-outline">
                    <x-icon name="upload" class="size-4" />
                    Import
                </button>

                <form
                    x-show="open"
                    x-transition.origin.top.right
                    x-cloak
                    @click.outside="open = false"
                    method="POST"
                    action="{{ route('admin.venues.import') }}"
                    enctype="multipart/form-data"
                    class="card absolute right-0 z-20 mt-2 w-80 space-y-3 p-4"
                >
                    @csrf
                    <div>
                        <p class="text-sm font-semibold">Import venues</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            An .xlsx or .csv with the directory’s column headers. Rows with a Venue ID that already exists are updated; the rest are added.
                        </p>
                    </div>

                    <input type="file" name="file" required accept=".xlsx,.csv" class="block w-full text-sm text-slate-600 file:mr-3 file:h-8 file:cursor-pointer file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:text-sm file:font-medium hover:file:bg-slate-200">
                    @error('file') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    <div class="flex items-center justify-between gap-2">
                        @can('venues.export')
                            <a href="{{ route('admin.venues.export', ['template' => 1]) }}" download class="text-xs font-medium text-brand-600 hover:underline">Blank template</a>
                        @endcan
                        <button class="btn-gold ml-auto">
                            <x-icon name="upload" class="size-4" />
                            Import
                        </button>
                    </div>
                </form>
            </div>
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

    @if ($import = session('import'))
        <div x-data="{ open: false }" class="card mt-4 p-4">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="badge-success">{{ $import['created'] }} added</span>
                <span class="badge-muted">{{ $import['updated'] }} updated</span>
                <span class="badge-muted">{{ $import['skipped'] }} empty rows skipped</span>
                @if ($import['errors'])
                    <span class="badge-pending">{{ count($import['errors']) }} rows not imported</span>
                    <button type="button" @click="open = ! open" class="ml-auto text-xs font-medium text-brand-600 hover:underline" x-text="open ? 'Hide problems' : 'Show problems'">Show problems</button>
                @endif
            </div>
            @if ($import['errors'])
                <ul x-show="open" x-cloak class="mt-3 max-h-64 space-y-1 overflow-auto rounded-lg bg-amber-50 p-3 text-xs text-amber-900">
                    @foreach ($import['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="mt-4 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <x-admin.stat label="Venues" :value="number_format($stats['total'])" :note="'Across '.$stats['states'].' '.str('state')->plural($stats['states'])" />
        <x-admin.stat label="Checked" :value="number_format($stats['checked'])" note="Every required detail confirmed" note-class="text-emerald-600" />
        <x-admin.stat label="To check" :value="number_format($stats['toCheck'])" note="Waiting to be verified" note-class="text-amber-600" />
        <x-admin.stat label="Courts listed" :value="number_format($stats['courts'])" note="Where the count is known" note-class="text-slate-500" />
    </div>

    <form method="GET" class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_repeat(3,10rem)]">
        <x-admin.search :value="$filters['search']" placeholder="Search by name, suburb, postcode or ID" />
        <x-admin.select name="state" :options="array_combine(\App\Models\Venue::STATES, \App\Models\Venue::STATES)" :selected="$filters['state']" placeholder="All states" />
        <x-admin.select name="setting" :options="array_combine(\App\Models\Venue::SETTINGS, \App\Models\Venue::SETTINGS)" :selected="$filters['setting']" placeholder="Indoor & outdoor" />
        <x-admin.select name="status" :options="array_combine(\App\Models\Venue::STATUSES, \App\Models\Venue::STATUSES)" :selected="$filters['status']" placeholder="Any status" />
    </form>

    <div class="card mt-3 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Venue</th>
                        <th class="px-4 py-2.5 font-medium">Location</th>
                        <th class="px-4 py-2.5 font-medium">Courts</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="px-4 py-2.5 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 border-t border-slate-200">
                    @forelse ($venues as $venue)
                        <tr class="transition hover:bg-slate-50/60">
                            <td class="max-w-80 px-4 py-2.5">
                                <a href="{{ route('admin.venues.show', $venue) }}" class="font-medium hover:underline">{{ $venue->name }}</a>
                                <p class="truncate text-xs text-slate-500">
                                    <span class="font-mono">{{ $venue->code }}</span>
                                    @if ($venue->address) · {{ $venue->address }} @endif
                                </p>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="text-slate-700">{{ $venue->suburb ?? '—' }}</span>
                                <span class="ml-1 text-xs text-slate-500">{{ $venue->state }} {{ $venue->postcode }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-600">
                                <span class="font-medium text-ink-950 tabular-nums">{{ $venue->court_count ?? '—' }}</span>
                                @if ($venue->indoor_outdoor)
                                    <span class="ml-1 text-xs text-slate-500">{{ $venue->indoor_outdoor }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5"><span class="{{ $venue->statusBadge() }}">{{ $venue->status }}</span></td>
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
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                                @if (array_filter($filters))
                                    No venues match your filters.
                                @else
                                    No venues yet. Add one, or import the directory spreadsheet.
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
