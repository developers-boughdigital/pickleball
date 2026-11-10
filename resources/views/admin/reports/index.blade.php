@php
    use App\Models\ListingReport;
@endphp

<x-admin.layout title="Reports" :breadcrumb="['Directory', 'Reports']">
    <x-admin.page-header title="Reports" subtitle="Courts visitors told us about and listings they say are wrong. Check each one, update the directory, then mark it resolved." />

    <nav class="mt-4 flex gap-1.5" aria-label="Report status">
        @foreach (['open' => 'Open', 'resolved' => 'Resolved'] as $key => $label)
            <a href="{{ route('admin.reports.index', ['status' => $key]) }}" @class([
                'inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-sm font-medium transition',
                'bg-ink-950 text-white' => $status === $key,
                'bg-white text-slate-600 ring-1 ring-slate-200 hover:text-ink-950' => $status !== $key,
            ])>
                {{ $label }}
                <span @class(['rounded-full px-1.5 text-xs tabular-nums', 'bg-white/20' => $status === $key, 'bg-slate-100' => $status !== $key])>{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <div class="mt-3 space-y-2">
        @forelse ($reports as $report)
            <article class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="{{ $report->kind === 'new_court' ? 'badge-gold' : 'badge-pending' }}">{{ ListingReport::KINDS[$report->kind] ?? $report->kind }}</span>
                        @if ($report->reportable)
                            <a href="{{ route('admin.venues.show', $report->reportable) }}" class="font-medium hover:underline">{{ $report->reportable->name }}</a>
                        @elseif ($report->area)
                            <span class="font-medium">{{ $report->area }}</span>
                        @endif
                        @if ($report->state) <span class="text-xs text-slate-500">{{ $report->state }}</span> @endif
                    </p>
                    <p class="text-sm whitespace-pre-line text-ink-950">{{ $report->details }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $report->name ?: 'Anonymous' }}
                        @if ($report->email) · <a href="mailto:{{ $report->email }}" class="hover:underline">{{ $report->email }}</a> @endif
                        · <span title="{{ $report->created_at->format('j M Y, g:i A') }}">{{ $report->created_at->diffForHumans() }}</span>
                        @if ($report->resolved_at) · resolved by {{ $report->resolver?->name ?? 'someone' }} {{ $report->resolved_at->diffForHumans() }} @endif
                    </p>
                </div>
                @can('venues.update')
                    <form method="POST" action="{{ route('admin.reports.update', $report) }}" class="shrink-0">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $report->status === 'open' ? 'resolved' : 'open' }}">
                        <button class="{{ $report->status === 'open' ? 'btn-gold' : 'btn-outline' }}">
                            <x-icon :name="$report->status === 'open' ? 'check' : 'rotate-ccw'" class="size-4" />
                            {{ $report->status === 'open' ? 'Mark resolved' : 'Reopen' }}
                        </button>
                    </form>
                @endcan
            </article>
        @empty
            <div class="card px-4 py-10 text-center text-sm text-slate-500">
                {{ $status === 'open' ? 'Nothing to check. Reports from the “Know a court we’re missing?” form on location pages land here.' : 'No resolved reports yet.' }}
            </div>
        @endforelse
    </div>

    @if ($reports->hasPages())
        <div class="card mt-3 overflow-hidden"><x-admin.pagination :paginator="$reports" noun="report" /></div>
    @endif
</x-admin.layout>
