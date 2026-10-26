<x-admin.layout title="Activity Log" :breadcrumb="['User & Security', 'Activity Log']">
    <x-admin.page-header title="Activity Log" subtitle="Sign-ins and every change to users, roles and permissions: who, what, when and from where." />

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Events today" :value="number_format($stats['today'])" note="All activity since midnight" />
        <x-admin.stat label="Failed today" :value="number_format($stats['failedToday'])" :note="$stats['failedToday'] ? 'Mostly failed sign-ins' : 'Nothing failed'" :note-class="$stats['failedToday'] ? 'text-red-600' : 'text-slate-500'" />
        <x-admin.stat label="Sign-ins today" :value="number_format($stats['signInsToday'])" note="Successful sign-ins" />
        <x-admin.stat label="All events" :value="number_format($stats['total'])" note="Since the log started" note-class="text-slate-500" />
    </div>

    <form method="GET" class="mt-4 flex flex-wrap gap-3 xl:flex-nowrap">
        <x-admin.search :value="$filters['search']" placeholder="Search activity" class="w-full xl:w-auto xl:min-w-0 xl:flex-[1.4]" />
        <x-admin.select name="range" :options="$ranges" :selected="$filters['range']" class="w-full sm:w-auto sm:flex-1 xl:min-w-0" />
        <x-admin.select name="actor" :options="$actors" :selected="$filters['actor']" placeholder="Everyone" class="w-full sm:w-auto sm:flex-1 xl:min-w-0" />
        <x-admin.select name="action" :options="$actions" :selected="$filters['action']" placeholder="All actions" class="w-full sm:w-auto sm:flex-1 xl:min-w-0" />
        <x-admin.select name="result" :options="$results" :selected="$filters['result']" placeholder="Any result" class="w-full sm:w-auto sm:flex-1 xl:min-w-0" />
        <a href="{{ route('admin.activity') }}" class="btn-outline h-9 w-full shrink-0 text-brand-600 sm:w-auto">Clear filters</a>
    </form>

    <div class="card mt-3 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Time</th>
                        <th class="px-4 py-2.5 font-medium">Who</th>
                        <th class="px-4 py-2.5 font-medium">What happened</th>
                        <th class="px-4 py-2.5 font-medium">Area</th>
                        <th class="px-4 py-2.5 font-medium">IP address</th>
                        <th class="px-4 py-2.5 font-medium">Result</th>
                    </tr>
                </thead>
                <tbody class="border-t border-slate-200">
                    @forelse ($events as $event)
                        @php($url = request()->fullUrlWithQuery(['event' => $event->id]))
                        @php($isSelected = $event->is($selected))
                        <tr @click="$event.target.closest('a') || $visit(@js($url))" @class([
                            'cursor-pointer border-b border-slate-100 transition last:border-0',
                            'bg-brand-50/60' => $isSelected,
                            'hover:bg-slate-50' => ! $isSelected,
                        ])>
                            <td class="px-4 py-2.5 whitespace-nowrap" title="{{ $event->created_at->format('j M Y, g:i:s A') }}">
                                <a href="{{ $url }}">{{ $event->created_at->isToday() ? 'Today '.$event->created_at->format('g:i A') : $event->created_at->format('j M, g:i A') }}</a>
                            </td>
                            <td class="px-4 py-2.5 font-medium">{{ $event->actor_name ?? 'Someone' }}</td>
                            <td class="px-4 py-2.5">{{ $event->description }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $event->module() }}</td>
                            <td class="px-4 py-2.5 text-slate-500 tabular-nums">{{ $event->ip_address ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <span class="{{ $event->succeeded ? 'badge-gold' : 'badge-danger' }}">{{ $event->succeeded ? 'Success' : 'Failed' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                                @if (array_filter(\Illuminate\Support\Arr::except($filters, 'range')))
                                    No activity matches your filters.
                                @else
                                    Nothing recorded in this period yet. Sign-ins and changes to users, roles and permissions will appear here.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$events" noun="event" />
    </div>

    @if ($selected)
        <section class="mt-4 rounded-xl border border-brand-100 bg-brand-50 p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold">{{ $selected->description }}</h2>
                    <p class="mt-0.5 text-sm text-slate-500">{{ $selected->actionLabel() }} · {{ $selected->created_at->format('l j F Y, g:i:s A') }}</p>
                </div>
                <span class="{{ $selected->succeeded ? 'badge-gold' : 'badge-danger' }}">{{ $selected->succeeded ? 'Success' : 'Failed' }}</span>
            </div>

            <dl class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    'Who' => $selected->actor_name ?? 'Someone (not signed in)',
                    'Record' => $selected->subject_label ? $selected->subject_type.': '.$selected->subject_label : '—',
                    'IP address' => $selected->ip_address ?? '—',
                    'Browser' => $selected->user_agent ? str($selected->user_agent)->limit(60) : '—',
                ] as $label => $value)
                    <div class="min-w-0">
                        <dt class="text-sm text-slate-500">{{ $label }}</dt>
                        <dd class="mt-0.5 font-medium break-words">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($selected->changes)
                @php($show = fn ($value) => is_array($value) ? (implode(', ', $value) ?: 'none') : ($value === null || $value === '' ? '—' : $value))
                <div class="mt-4 overflow-x-auto rounded-lg border border-brand-100 bg-white">
                    <table class="w-full min-w-[480px] text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-3 py-2 font-medium">Field</th>
                                <th class="px-3 py-2 font-medium">Before</th>
                                <th class="px-3 py-2 font-medium">After</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($selected->changes as $field => $change)
                                <tr>
                                    <td class="px-3 py-2 font-medium">{{ str($field)->headline() }}</td>
                                    <td class="px-3 py-2 text-slate-500">{{ $show($change['from'] ?? null) }}</td>
                                    <td class="px-3 py-2">{{ $show($change['to'] ?? null) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</x-admin.layout>
