@php
    use App\Http\Controllers\Admin\ImportController;

    $help = [
        'venues' => 'Every venue. Matched on ref (V0001): existing venues are updated and their slug never changes; new ones are added as drafts.',
        'clubs' => 'Every club. Matched on ref (C0001): existing clubs are updated and their slug never changes; new ones are added as drafts.',
        'club_venue' => 'Which venues each club plays at (is_primary = 1 for the main venue). Import venues and clubs first. For every club in the file, links that are no longer listed are removed.',
    ];
    $countLabel = ['venues' => 'venues', 'clubs' => 'clubs', 'club_venue' => 'links'];
@endphp

<x-admin.layout title="Import" :breadcrumb="['Directory', 'Import']">
    <x-admin.page-header title="Import" subtitle="Load the directory import pack. Run venues.csv, then clubs.csv, then club_venue.csv." />

    @if ($import = session('import'))
        <div x-data="{ open: false }" class="card mt-4 p-4">
            <p class="text-sm font-semibold">{{ ImportController::TYPES[$import['type']][0] }} · {{ $import['file'] }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                @if ($import['type'] === 'club_venue')
                    <span class="badge-success">{{ $import['created'] }} links added</span>
                    <span class="badge-muted">{{ $import['updated'] }} main venue changes</span>
                    <span class="badge-muted">{{ $import['removed'] }} removed</span>
                    <span class="badge-muted">{{ $import['clubs'] }} clubs</span>
                @else
                    <span class="badge-success">{{ $import['created'] }} added</span>
                    <span class="badge-muted">{{ $import['updated'] }} updated</span>
                    <span class="badge-muted">{{ $import['drafts'] }} in draft</span>
                    <span class="badge-success">{{ $import['checked'] }} checked, ready to go live</span>
                @endif
                @if ($import['skipped'])
                    <span class="badge-muted">{{ $import['skipped'] }} empty rows skipped</span>
                @endif
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

    <form method="POST" action="{{ route('admin.import.store') }}" enctype="multipart/form-data" x-data="{ type: @js($selected), busy: false, dirty: false }" @submit="busy = true" data-reload class="mt-4 space-y-3">
        @csrf

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">1. What are you importing?</h2>
            <div class="grid gap-3 md:grid-cols-3">
                @foreach ($types as $step => $type)
                    @php([$label, $file] = ImportController::TYPES[$type])
                    <label class="flex cursor-pointer flex-col gap-1 rounded-xl border border-slate-200 p-4 transition hover:border-slate-300 has-checked:border-brand-500 has-checked:bg-brand-50 has-checked:ring-2 has-checked:ring-brand-300">
                        <span class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-sm font-semibold">
                                <input type="radio" name="type" value="{{ $type }}" x-model="type" class="accent-emerald-600">
                                {{ $label }}
                            </span>
                            <span class="font-mono text-xs text-slate-500">{{ $file }}</span>
                        </span>
                        <span class="text-xs text-slate-500">{{ $help[$type] }}</span>
                        <span class="mt-1 text-xs font-medium text-slate-600">{{ number_format($counts[$type]) }} {{ $countLabel[$type] }} now</span>
                    </label>
                @endforeach
            </div>
            @error('type') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </section>

        <section class="card p-4 sm:p-5">
            <h2 class="mb-3 text-sm font-semibold">2. Choose the file</h2>
            <input type="file" name="file" required accept=".xlsx,.csv" class="block w-full text-sm text-slate-600 file:mr-3 file:h-9 file:cursor-pointer file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:text-sm file:font-medium hover:file:bg-slate-200">
            @error('file') <p class="mt-2 flex items-center gap-1 text-xs text-red-600"><x-icon name="triangle-alert" class="size-3.5" /> {{ $message }}</p> @enderror
            <p class="mt-2 text-xs text-slate-500">
                .csv or .xlsx, up to 10 MB, with the pack’s column names in the first row. Empty cells mean unknown.
                @foreach ($types as $type)
                    <a x-show="type === @js($type)" href="{{ route('admin.import.template', $type) }}" download class="font-medium text-brand-600 hover:underline">Download a blank {{ ImportController::TYPES[$type][1] }}</a>
                @endforeach
            </p>
        </section>

        <x-admin.form-actions>
            <button class="btn-gold" :disabled="busy">
                <x-icon name="upload" class="size-4" />
                <span x-text="busy ? 'Importing…' : 'Import'">Import</span>
            </button>
        </x-admin.form-actions>
    </form>
</x-admin.layout>
