<x-admin.layout title="Database" :breadcrumb="['System', 'Database']">
    <x-admin.page-header title="Database" subtitle="Run migrations and seeders. Only Platform admins can see this page.">
        <form method="POST" action="{{ route('admin.database.migrate') }}">
            @csrf
            <button class="btn-gold" @disabled(! $pending)>
                <x-icon name="play" class="size-4" />
                Run pending migrations
            </button>
        </form>
    </x-admin.page-header>

    @if (app()->isProduction())
        <p class="mt-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            <x-icon name="triangle-alert" class="size-4" />
            This is the live database. Back it up before changing anything.
        </p>
    @endif

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Connection" :value="$connection['driver']" :note="$connection['database']" note-class="text-slate-500" />
        <x-admin.stat label="Migrations run" :value="$migrations->where('ran', true)->count()" :note="'of '.$migrations->count().' files'" />
        <x-admin.stat label="Pending" :value="$pending" :note="$pending ? 'Waiting to run' : 'Up to date'" :note-class="$pending ? 'text-red-600' : 'text-emerald-600'" />
        <x-admin.stat label="Last batch" :value="$lastBatch ?? '—'" note="Rolled back as one" note-class="text-slate-500" />
    </div>

    @if ($console = session('console'))
        <section class="mt-4 overflow-hidden rounded-xl bg-ink-950 text-slate-300">
            <div class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-2">
                <p class="flex items-center gap-2 font-mono text-xs">
                    <x-icon name="terminal" class="size-4 text-brand-400" />
                    {{ $console['command'] }}
                </p>
                <span class="{{ $console['succeeded'] ? 'badge-gold' : 'badge-danger' }}">{{ $console['succeeded'] ? 'Done' : 'Failed' }}</span>
            </div>
            <pre class="max-h-72 overflow-auto px-4 py-3 font-mono text-xs leading-relaxed whitespace-pre-wrap">{{ $console['output'] }}</pre>
        </section>
    @endif

    <div class="mt-4 grid gap-3 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        {{-- Migrations --}}
        <section class="card min-w-0 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <h2 class="text-sm font-semibold">Migrations</h2>
                <form
                    method="POST"
                    action="{{ route('admin.database.rollback') }}"
                    @submit="confirm(@js("Roll back batch {$lastBatch}? Tables and columns it created will be dropped, along with their data.")) || $event.preventDefault()"
                >
                    @csrf
                    <button class="btn-outline h-7 px-2.5 text-xs text-red-600 hover:bg-red-50" @disabled(! $lastBatch)>
                        <x-icon name="rotate-ccw" class="size-3.5" />
                        Roll back last batch
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto border-t border-slate-200">
                <table class="w-full min-w-[520px] text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">Migration</th>
                            <th class="px-4 py-2 font-medium">Batch</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="w-12 px-4 py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($migrations as $migration)
                            <tr @class([
                                'border-l-4',
                                'border-l-emerald-500 bg-emerald-50/60' => $migration['ran'],
                                'border-l-amber-400 bg-amber-50/60' => ! $migration['ran'],
                            ])>
                                <td @class(['px-4 py-2 font-mono text-xs', 'text-emerald-900' => $migration['ran'], 'text-amber-900' => ! $migration['ran']])>{{ $migration['name'] }}</td>
                                <td class="px-4 py-2 tabular-nums text-slate-500">{{ $migration['batch'] ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    @if ($migration['ran'])
                                        <span class="badge-success"><x-icon name="check" class="size-3" /> Ran</span>
                                    @else
                                        <span class="badge-pending">Pending</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1 text-right">
                                    @if ($migration['ran'])
                                        <form
                                            method="POST"
                                            action="{{ route('admin.database.rollback-migration') }}"
                                            @submit="confirm(@js("Roll back {$migration['name']}? Whatever it created will be dropped, along with its data.")) || $event.preventDefault()"
                                        >
                                            @csrf
                                            <input type="hidden" name="migration" value="{{ $migration['name'] }}">
                                            <button class="icon-btn-delete ml-auto" title="Roll back this migration" aria-label="Roll back {{ $migration['name'] }}">
                                                <x-icon name="rotate-ccw" class="size-4" />
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No migration files found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Seeders --}}
        <section class="card min-w-0 self-start overflow-hidden">
            <h2 class="px-4 py-3 text-sm font-semibold">Seeders</h2>

            <ul class="divide-y divide-slate-100 border-t border-slate-200">
                @forelse ($seeders as $class => $label)
                    <li class="flex items-center gap-3 px-4 py-2.5">
                        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600">
                            <x-icon name="sprout" class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $label }}</p>
                            <code class="block truncate text-[11px] text-slate-400">{{ $class }}</code>
                        </div>
                        <form method="POST" action="{{ route('admin.database.seed') }}" @submit="confirm(@js("Run {$class}? It writes data to the database.")) || $event.preventDefault()">
                            @csrf
                            <input type="hidden" name="seeder" value="{{ $class }}">
                            <button class="btn-outline h-7 px-2.5 text-xs">
                                <x-icon name="play" class="size-3" />
                                Run
                            </button>
                        </form>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-slate-500">No seeders found.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-admin.layout>
