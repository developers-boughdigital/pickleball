<x-admin.layout title="Activity Log" :breadcrumb="['Admin', 'User & Security']">
    <x-admin.page-header title="Activity Log" subtitle="Every admin action on other people’s data: who, what, when and from where.">
        <button type="button" class="btn-gold">
            <x-icon name="download" />
            Export activity
        </button>
    </x-admin.page-header>

    <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Events today" value="186" note="Across all modules" note-class="text-brand-500" />
        <x-admin.stat label="Failed actions" value="7" note="Needs review" note-class="text-red-600" />
        <x-admin.stat label="Support impersonations" value="2" note="This week" note-class="text-brand-500" />
        <x-admin.stat label="Retention" value="180 days" note="Audit history" note-class="text-slate-500" />
    </div>

    <form method="GET" class="mt-8 flex flex-wrap gap-3 xl:flex-nowrap">
        <x-admin.search :value="$filters['search'] ?? ''" placeholder="Search activity" class="w-full xl:w-auto xl:min-w-0 xl:flex-[1.4]" />
        <x-admin.select name="range" :options="$ranges" :selected="$filters['range'] ?? '30'" class="w-full sm:w-auto sm:flex-1 xl:min-w-0" />
        <x-admin.select name="actor" :options="$actors" :selected="$filters['actor'] ?? null" placeholder="All actors" class="w-full sm:w-auto sm:flex-1 xl:min-w-0" />
        <x-admin.select name="module" :options="$modules" :selected="$filters['module'] ?? null" placeholder="All modules" class="w-full sm:w-auto sm:flex-1 xl:min-w-0" />
        <x-admin.select name="action" :options="$actions" :selected="$filters['action'] ?? null" placeholder="All actions" class="w-full sm:w-auto sm:flex-1 xl:min-w-0" />
        <a href="{{ route('admin.activity') }}" class="btn-outline w-full shrink-0 text-brand-500 sm:w-auto">Clear filters</a>
    </form>

    <div class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left text-[15px]">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-6 py-5 font-medium">Time</th>
                        <th class="px-6 py-5 font-medium">Actor</th>
                        <th class="px-6 py-5 font-medium">Action</th>
                        <th class="px-6 py-5 font-medium">Module</th>
                        <th class="px-6 py-5 font-medium">Target</th>
                        <th class="px-6 py-5 font-medium">IP address</th>
                        <th class="px-6 py-5 font-medium">Result</th>
                    </tr>
                </thead>
                <tbody class="border-t border-slate-200">
                    @forelse ($events as $event)
                        @php($url = request()->fullUrlWithQuery(['event' => $event['id']]))
                        <tr @click="$event.target.closest('a') || $visit(@js($url))" @class([
                            'cursor-pointer transition',
                            'bg-brand-50/60' => $event['id'] === $selected['id'],
                            'hover:bg-slate-50' => $event['id'] !== $selected['id'],
                        ])>
                            <td class="px-6 py-5"><a href="{{ $url }}">{{ $event['time'] }}</a></td>
                            <td class="px-6 py-5 font-medium">{{ $event['actor'] }}</td>
                            <td class="px-6 py-5">{{ $event['action'] }}</td>
                            <td class="px-6 py-5">{{ $event['module'] }}</td>
                            <td class="px-6 py-5">{{ $event['target'] }}</td>
                            <td class="px-6 py-5 text-slate-500 tabular-nums">{{ $event['ip'] }}</td>
                            <td class="px-6 py-5">
                                <span class="{{ $event['result'] === 'Success' ? 'badge-gold' : 'badge-danger' }}">{{ $event['result'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-500">No activity matches your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-4 border-t border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[15px] text-slate-500">
                @if ($isFiltered)
                    {{ $events->count() }} matching {{ Str::plural('event', $events->count()) }}
                @else
                    1–{{ $events->count() }} of 186 events
                @endif
            </p>

            <nav class="flex items-center gap-2" aria-label="Pagination">
                <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" aria-label="Previous page">
                    <x-icon name="chevron-left" class="size-4" />
                </button>
                <span class="grid size-10 place-items-center rounded-lg bg-brand-500 font-medium text-ink-950" aria-current="page">1</span>
                @foreach ([2, 3] as $page)
                    <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 hover:bg-slate-50">{{ $page }}</button>
                @endforeach
                <span class="px-1 text-slate-500">…</span>
                <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 hover:bg-slate-50">6</button>
                <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" aria-label="Next page">
                    <x-icon name="chevron-right" class="size-4" />
                </button>
            </nav>
        </div>
    </div>

    {{-- Event details --}}
    <section class="mt-8 rounded-2xl border border-brand-100 bg-brand-50 p-6 shadow-[0_1px_3px_rgb(15_23_42/0.06)] sm:p-10">
        <h2 class="text-xl font-semibold">Event details</h2>
        <p class="mt-1 text-[15px] text-slate-500">Selected activity event</p>

        <dl class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
            @foreach (['Actor' => $selected['actor'], 'Timestamp' => $selected['timestamp'], 'IP address' => $selected['ip'], 'Change summary' => $selected['summary']] as $label => $value)
                <div>
                    <dt class="text-sm text-slate-500">{{ $label }}</dt>
                    <dd class="mt-1.5 text-base font-medium sm:text-[17px]">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</x-admin.layout>
