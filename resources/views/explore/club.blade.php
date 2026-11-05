@php
    use App\Models\Club;

    $back = route('explore', $drafts ? ['drafts' => 1] : []);
    $short = fn (string $url) => preg_replace('~^https?://(www\.)?~', '', rtrim($url, '/'));
    $features = array_keys(array_filter(Club::FEATURES, fn ($label, $key) => $club->getAttribute($key) === true, ARRAY_FILTER_USE_BOTH));
    $fees = array_filter([
        'Casual session' => $club->money('casual_fee_cents'),
        'Membership (per year)' => $club->money('membership_fee_cents'),
        'Court hire (per hour)' => $club->money('court_hire_cents'),
    ]);
    $socials = array_filter(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'], fn ($label, $key) => filled($club->getAttribute($key)), ARRAY_FILTER_USE_BOTH);
@endphp

<x-site :title="$club->name" :description="$club->short_description ?: 'Pickleball club in '.($club->location() ?: 'Australia').'.'">
    <section class="bg-ink-950 text-white">
        <div class="mx-auto max-w-6xl px-4 py-8">
            <a href="{{ $back }}" class="inline-flex items-center gap-1 text-sm text-white/60 hover:text-white">
                <x-icon name="chevron-left" class="size-4" /> All clubs
            </a>
            <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold tracking-wide text-brand-300 uppercase">Pickleball club</p>
                    <h1 class="mt-1 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $club->name }}</h1>
                    <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-white/70">
                        @if ($club->location()) <span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="size-4" />{{ $club->location() }}</span> @endif
                        @if ($club->year_established) <span>Since {{ $club->year_established }}</span> @endif
                        @if ($club->member_count) <span>{{ number_format($club->member_count) }} members</span> @endif
                        @if ($drafts && $club->status !== 'published') <span class="badge-muted">Draft</span> @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($club->booking_url)
                        <a href="{{ $club->booking_url }}" target="_blank" rel="noopener" class="inline-flex h-11 items-center gap-2 rounded-xl bg-linear-to-r from-brand-500 to-brand-300 px-5 text-sm font-bold text-ink-950 transition hover:brightness-105">
                            Book a session <x-icon name="external-link" class="size-4" />
                        </a>
                    @endif
                    @if ($club->website)
                        <a href="{{ $club->website }}" target="_blank" rel="noopener" class="inline-flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-semibold ring-1 ring-white/25 transition hover:bg-white/10">
                            Website <x-icon name="external-link" class="size-4" />
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto grid max-w-6xl gap-4 px-4 py-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($club->short_description || $club->description)
                <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                    <h2 class="font-bold">About</h2>
                    @if ($club->short_description) <p class="mt-2 text-slate-700">{{ $club->short_description }}</p> @endif
                    @if ($club->description) <p class="mt-2 whitespace-pre-line text-slate-600">{{ $club->description }}</p> @endif
                </section>
            @endif

            <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                <h2 class="font-bold">Play</h2>
                @if ($club->levels)
                    <p class="mt-3 text-sm text-slate-500">Levels</p>
                    <ul class="mt-1 flex flex-wrap gap-1.5 text-sm font-medium">
                        @foreach ($club->levels as $level)
                            <li class="rounded-lg bg-slate-100 px-2.5 py-1">{{ Club::LEVELS[$level] ?? $level }}</li>
                        @endforeach
                    </ul>
                @endif
                @if ($club->session_times_text)
                    <p class="mt-4 text-sm text-slate-500">Sessions</p>
                    <p class="mt-1 whitespace-pre-line text-slate-700">{{ $club->session_times_text }}</p>
                @endif
                @if ($club->leagues_text)
                    <p class="mt-4 text-sm text-slate-500">Leagues & competition</p>
                    <p class="mt-1 text-slate-700">{{ $club->leagues_text }}</p>
                @endif
                @if ($club->coach_names_text)
                    <p class="mt-4 text-sm text-slate-500">Coaches</p>
                    <p class="mt-1 text-slate-700">{{ $club->coach_names_text }}</p>
                @endif
                @if ($features)
                    <ul class="mt-4 flex flex-wrap gap-1.5 text-sm font-medium">
                        @foreach ($features as $feature)
                            <li class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 text-emerald-700"><x-icon name="check" class="size-3.5" />{{ Club::FEATURES[$feature] }}</li>
                        @endforeach
                    </ul>
                @endif
                @unless ($club->levels || $club->session_times_text || $features)
                    <p class="mt-2 text-sm text-slate-500">Session details are coming soon. Contact the club to find out when they play.</p>
                @endunless
            </section>

            <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                <h2 class="font-bold">Where they play</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @forelse ($club->venues as $venue)
                        <a href="{{ route('explore.venue', [$venue, ...($drafts ? ['drafts' => 1] : [])]) }}" class="rounded-xl p-3 ring-1 ring-slate-200 transition hover:bg-slate-50 hover:ring-slate-300">
                            <span class="flex items-center justify-between gap-2">
                                <span class="font-semibold">{{ $venue->name }}</span>
                                @if ($venue->pivot->is_primary) <span class="badge-success">Home venue</span> @endif
                            </span>
                            <span class="mt-0.5 block text-sm text-slate-500">{{ $venue->fullAddress() }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-slate-500">Venue details are coming soon.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-4">
            @if ($fees || $club->membership_notes || $club->booking_method)
                <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                    <h2 class="font-bold">Fees & joining</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        @foreach ($fees as $label => $amount)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $label }}</dt><dd class="font-semibold">{{ $amount }}</dd></div>
                        @endforeach
                        @if ($club->booking_method)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">How to play</dt><dd class="font-semibold">{{ $club->label('booking_method') }}</dd></div>
                        @endif
                    </dl>
                    @if ($club->membership_notes)
                        <p class="mt-3 text-sm text-slate-600">{{ $club->membership_notes }}</p>
                    @endif
                </section>
            @endif

            <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                <h2 class="font-bold">Get in touch</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @if ($club->email)
                        <li><a href="mailto:{{ $club->email }}" class="inline-flex items-center gap-2 break-all hover:underline"><x-icon name="mail" class="size-4 shrink-0 text-slate-400" />{{ $club->email }}</a></li>
                    @endif
                    @if ($club->phone)
                        <li><a href="tel:{{ preg_replace('/[^\d+]/', '', $club->phone) }}" class="inline-flex items-center gap-2 hover:underline"><x-icon name="phone" class="size-4 text-slate-400" />{{ $club->phone }}</a></li>
                    @endif
                    @if ($club->website)
                        <li><a href="{{ $club->website }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 break-all hover:underline"><x-icon name="globe" class="size-4 shrink-0 text-slate-400" />{{ $short($club->website) }}</a></li>
                    @endif
                    @foreach ($socials as $key => $label)
                        <li><a href="{{ $club->getAttribute($key) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 hover:underline"><x-icon name="external-link" class="size-4 text-slate-400" />{{ $label }}</a></li>
                    @endforeach
                    @unless ($club->email || $club->phone || $club->website || $socials)
                        <li class="text-slate-500">Contact details are coming soon.</li>
                    @endunless
                </ul>
            </section>
        </aside>
    </div>
</x-site>
