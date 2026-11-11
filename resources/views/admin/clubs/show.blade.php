@php
    use App\Models\Club;

    $yesNo = fn (?bool $value) => $value === null ? 'Unknown' : ($value ? 'Yes' : 'No');
    $details = fn (array $rows) => array_filter($rows, fn ($value) => filled($value));
    $short = fn (string $url) => preg_replace('~^https?://(www\.)?~', '', rtrim($url, '/'));
@endphp

<x-admin.layout :title="$club->name" :breadcrumb="['Clubs', $club->name]">
    <a href="{{ route('admin.clubs.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Clubs
    </a>

    <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <x-admin.form-heading icon="users">
            <h1 class="text-xl font-bold tracking-tight">{{ $club->name }}</h1>
            <p class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <span class="font-mono">{{ $club->ref }}</span>
                <span class="{{ $club->statusBadge() }}">{{ $club->label('status') }}</span>
                <span class="{{ $club->recordStatusBadge() }}">{{ $club->label('record_status') }}</span>
                @if ($club->location()) <span>{{ $club->location() }}</span> @endif
            </p>
        </x-admin.form-heading>

        <div class="flex items-center gap-2">
            <x-admin.publish-toggle type="clubs" :listing="$club" labelled />
            @can('clubs.delete')
                <form method="POST" action="{{ route('admin.clubs.destroy', $club) }}" @submit="confirm(@js("Delete {$club->name}?")) || $event.preventDefault()">
                    @csrf
                    @method('DELETE')
                    <button class="btn-outline text-red-600 hover:bg-red-50">
                        <x-icon name="trash" class="size-4" />
                        Delete
                    </button>
                </form>
            @endcan
            @can('clubs.update')
                <a href="{{ route('admin.clubs.edit', $club) }}" class="btn-gold">
                    <x-icon name="pencil" class="size-4" />
                    Edit
                </a>
            @endcan
        </div>
    </div>

    <div class="mt-4 grid gap-3 xl:grid-cols-3">
        <div class="space-y-3 xl:col-span-2">
            @if ($club->short_description || $club->description)
                <section class="card space-y-2 p-4 text-sm sm:p-5">
                    @if ($club->short_description) <p class="font-medium">{{ $club->short_description }}</p> @endif
                    @if ($club->description) <p class="whitespace-pre-line text-slate-600">{{ $club->description }}</p> @endif
                </section>
            @endif

            <x-admin.location-pages :listing="$club" />

            <section class="card p-4 sm:p-5">
                <h2 class="mb-3 text-sm font-semibold">Where they play</h2>
                @forelse ($club->venues as $venue)
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 text-sm last:border-0">
                        <div class="min-w-0">
                            @can('venues.view')
                                <a href="{{ route('admin.venues.show', $venue) }}" class="font-medium hover:underline">{{ $venue->name }}</a>
                            @else
                                <span class="font-medium">{{ $venue->name }}</span>
                            @endcan
                            <span class="ml-1 font-mono text-xs text-slate-500">{{ $venue->ref }}</span>
                            <p class="truncate text-xs text-slate-500">{{ $venue->fullAddress() }}</p>
                        </div>
                        @if ($venue->pivot->is_primary)
                            <span class="badge-success shrink-0">Main venue</span>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No venues linked yet. Import club_venue.csv, or add venue refs when editing the club.</p>
                @endforelse
            </section>

            <section class="card p-4 sm:p-5">
                <h2 class="mb-3 text-sm font-semibold">Play</h2>
                <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    @foreach ($details([
                        'Levels' => $club->levelNames(),
                        'Session types' => implode(', ', $club->session_types ?? []),
                        'Leagues' => $club->leagues_text,
                        'Coaches' => $club->coach_names_text,
                        'State affiliation' => $club->state_affiliation,
                    ]) as $label => $text)
                        <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                            <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                            <dd class="text-right">{{ $text }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($club->session_times_text)
                    <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-line">{{ $club->session_times_text }}</p>
                @endif

                <ul class="mt-3 flex flex-wrap gap-1.5">
                    @foreach (Club::FEATURES as $key => $label)
                        @php($has = $club->getAttribute($key))
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
                <h2 class="mb-3 text-sm font-semibold">Fees & booking</h2>
                <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    @forelse ($details([
                        'Membership (per year)' => $club->money('membership_fee_cents'),
                        'Casual (per session)' => $club->money('casual_fee_cents'),
                        'Court hire (per hour)' => $club->money('court_hire_cents'),
                        'Booking' => $club->label('booking_method'),
                    ]) as $label => $text)
                        <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                            <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                            <dd class="text-right">{{ $text }}</dd>
                        </div>
                    @empty
                        <p class="text-slate-500">No fees or booking details yet.</p>
                    @endforelse
                </dl>
                @if ($club->membership_notes)
                    <p class="mt-3 text-sm text-slate-600">{{ $club->membership_notes }}</p>
                @endif
                <div class="mt-3 flex flex-wrap gap-3 text-xs font-medium">
                    @foreach ($details(['Book a session' => $club->booking_url, 'Calendar' => $club->calendar_url]) as $label => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-brand-600 hover:underline">{{ $label }} <x-icon name="external-link" class="size-3.5" /></a>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="space-y-3">
            <section class="card p-4 sm:p-5">
                <h2 class="mb-3 text-sm font-semibold">Contact</h2>
                <dl class="space-y-2 text-sm">
                    @forelse ($details([
                        'Email' => $club->email,
                        'Phone' => $club->phone,
                        ...array_combine(Club::SOCIALS, array_map(fn ($key) => $club->getAttribute($key), array_keys(Club::SOCIALS))),
                        'Founded' => $club->year_established,
                        'Members' => $club->member_count ? number_format($club->member_count) : null,
                    ]) as $label => $text)
                        <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                            <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                            <dd class="min-w-0 text-right">
                                @if (in_array($label, Club::SOCIALS))
                                    <a href="{{ $text }}" target="_blank" rel="noopener" class="block truncate text-brand-600 hover:underline">{{ $short($text) }}</a>
                                @elseif ($label === 'Email')
                                    <a href="mailto:{{ $text }}" class="block truncate hover:underline">{{ $text }}</a>
                                @elseif ($label === 'Phone')
                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $text) }}" class="hover:underline">{{ $text }}</a>
                                @else
                                    {{ $text }}
                                @endif
                            </dd>
                        </div>
                    @empty
                        <p class="text-slate-500">No contact details yet.</p>
                    @endforelse
                </dl>
            </section>

            <section class="card p-4 sm:p-5">
                <h2 class="mb-3 text-sm font-semibold">Verification</h2>
                <dl class="space-y-2 text-sm">
                    @foreach ([
                        'Record status' => $club->label('record_status'),
                        'Claim status' => $club->label('claim_status'),
                        'Profile tier' => $club->profile_tier,
                        'Source' => $club->source_type,
                        'Date checked' => $club->checked_at?->format('j M Y'),
                        'Checked by' => $club->checked_by,
                        'Date enriched' => $club->enriched_at?->format('j M Y'),
                        'Last updated' => $club->updated_at?->diffForHumans(),
                    ] as $label => $text)
                        <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                            <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                            <dd class="text-right">{{ $text ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if (str_starts_with((string) $club->source_url, 'http'))
                    <a href="{{ $club->source_url }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline">
                        View source <x-icon name="external-link" class="size-3.5" />
                    </a>
                @elseif ($club->source_url)
                    <p class="mt-3 text-xs text-slate-500">Source: {{ $club->source_url }}</p>
                @endif
                @if ($club->enrichment_evidence)
                    <details class="mt-3 text-xs">
                        <summary class="cursor-pointer font-medium text-slate-600">Enrichment evidence (admin only)</summary>
                        <p class="mt-2 rounded-lg bg-slate-50 p-3 break-words whitespace-pre-line text-slate-600">{{ $club->enrichment_evidence }}</p>
                    </details>
                @endif
                @if ($club->notes)
                    <p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs break-words whitespace-pre-line text-amber-900">{{ $club->notes }}</p>
                @endif
            </section>
        </div>
    </div>
</x-admin.layout>
