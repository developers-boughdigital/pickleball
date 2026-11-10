@php
    use App\Models\Club;

    $back = route('explore.clubs', $drafts ? ['drafts' => 1] : []);
    $short = fn (string $url) => preg_replace('~^https?://(www\.)?~', '', rtrim($url, '/'));
    $features = array_keys(array_filter(Club::FEATURES, fn ($label, $key) => $club->getAttribute($key) === true, ARRAY_FILTER_USE_BOTH));
    $fees = array_filter([
        'Casual session' => $club->money('casual_fee_cents'),
        'Membership (per year)' => $club->money('membership_fee_cents'),
        'Court hire (per hour)' => $club->money('court_hire_cents'),
    ]);
    $socials = array_filter(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'], fn ($label, $key) => filled($club->getAttribute($key)), ARRAY_FILTER_USE_BOTH);

    $url = route('explore.club', $club);
    $crumbs = [['Home', route('home')], ['Clubs', route('explore.clubs')], [$club->name, $url]];
    $schema = [\App\Support\Seo\Schema::club($club, $url, $club->venues, fn ($venue) => route('explore.venue', $venue))];
@endphp

<x-site
    :title="$club->meta_title ?: $club->name.($club->suburb ? ', '.$club->suburb : '')"
    :description="$club->meta_description ?: ($club->short_description ?: 'Pickleball club in '.($club->location() ?: 'Australia').': when they play, levels, fees and how to join.')"
    :canonical="$url" :noindex="$club->status !== 'published'" :breadcrumbs="$crumbs" :schema="$schema">
    <section class="border-b border-line bg-white">
        <div class="site-wrap pt-6 pb-8">
            <nav aria-label="Breadcrumb" class="flex flex-wrap gap-2 text-muted">
                <a href="{{ route('home') }}" class="site-link font-normal">Home</a><span aria-hidden="true">›</span>
                <a href="{{ $back }}" class="site-link font-normal">Clubs</a><span aria-hidden="true">›</span>
                <span class="font-bold text-navy" aria-current="page">{{ $club->name }}</span>
            </nav>
            <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="font-bold text-gum-text">Pickleball club</p>
                    <h1 class="mt-1 font-display text-[40px] leading-[44px] font-bold sm:text-5xl sm:leading-[52px]">{{ $club->name }}</h1>
                    <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-lg text-muted">
                        @if ($club->location()) <span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="size-4" />{{ $club->location() }}</span> @endif
                        @if ($club->year_established) <span>Since {{ $club->year_established }}</span> @endif
                        @if ($club->member_count) <span>{{ number_format($club->member_count) }} members</span> @endif
                        @if ($drafts && $club->status !== 'published') <span class="badge-muted">Draft</span> @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($club->booking_url)
                        <a href="{{ $club->booking_url }}" target="_blank" rel="noopener" class="site-btn-primary">
                            Book a session <x-icon name="external-link" class="size-4" />
                        </a>
                    @endif
                    @if ($club->website)
                        <a href="{{ $club->website }}" target="_blank" rel="noopener" class="site-btn-outline">
                            Website <x-icon name="external-link" class="size-4" />
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="site-wrap grid gap-6 py-8 pb-16 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($club->short_description || $club->description)
                <section class="site-card p-6">
                    <h2 class="site-h2">About</h2>
                    @if ($club->short_description) <p class="mt-2 text-navy">{{ $club->short_description }}</p> @endif
                    @if ($club->description) <p class="mt-2 whitespace-pre-line text-navy">{{ $club->description }}</p> @endif
                </section>
            @endif

            <section class="site-card p-6">
                <h2 class="site-h2">Play</h2>
                @if ($club->levels)
                    <p class="mt-3 text-muted">Levels</p>
                    <ul class="mt-1 flex flex-wrap gap-1.5 font-medium">
                        @foreach ($club->levels as $level)
                            <li><x-site.level :level="$level" /></li>
                        @endforeach
                    </ul>
                @endif
                @if ($club->session_times_text)
                    <p class="mt-4 text-muted">Sessions</p>
                    <p class="mt-1 whitespace-pre-line text-navy">{{ $club->session_times_text }}</p>
                @endif
                @if ($club->leagues_text)
                    <p class="mt-4 text-muted">Leagues & competition</p>
                    <p class="mt-1 text-navy">{{ $club->leagues_text }}</p>
                @endif
                @if ($club->coach_names_text)
                    <p class="mt-4 text-muted">Coaches</p>
                    <p class="mt-1 text-navy">{{ $club->coach_names_text }}</p>
                @endif
                @if ($features)
                    <ul class="mt-4 flex flex-wrap gap-1.5 font-medium">
                        @foreach ($features as $feature)
                            <li class="inline-flex items-center gap-1 site-tag gap-1 bg-gum-soft text-gum-text"><x-icon name="check" class="size-3.5" />{{ Club::FEATURES[$feature] }}</li>
                        @endforeach
                    </ul>
                @endif
                @unless ($club->levels || $club->session_times_text || $features)
                    <p class="mt-2 text-muted">Session details are coming soon. Contact the club to find out when they play.</p>
                @endunless
            </section>

            <section class="site-card p-6">
                <h2 class="site-h2">Where they play</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @forelse ($club->venues as $venue)
                        <a href="{{ route('explore.venue', [$venue, ...($drafts ? ['drafts' => 1] : [])]) }}" class="rounded-xl p-4 text-navy no-underline ring-2 ring-line transition hover:bg-court-soft hover:ring-court">
                            <span class="flex items-center justify-between gap-2">
                                <span class="site-h3 text-court">{{ $venue->name }}</span>
                                @if ($venue->pivot->is_primary) <span class="badge-success">Home venue</span> @endif
                            </span>
                            <span class="mt-0.5 block text-muted">{{ $venue->fullAddress() }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-muted">Venue details are coming soon.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-4">
            @if ($fees || $club->membership_notes || $club->booking_method)
                <section class="site-card p-6">
                    <h2 class="site-h2">Fees & joining</h2>
                    <dl class="mt-3 space-y-2">
                        @foreach ($fees as $label => $amount)
                            <div class="flex justify-between gap-3"><dt class="text-muted">{{ $label }}</dt><dd class="font-bold">{{ $amount }}</dd></div>
                        @endforeach
                        @if ($club->booking_method)
                            <div class="flex justify-between gap-3"><dt class="text-muted">How to play</dt><dd class="font-bold">{{ $club->label('booking_method') }}</dd></div>
                        @endif
                    </dl>
                    @if ($club->membership_notes)
                        <p class="mt-3 text-navy">{{ $club->membership_notes }}</p>
                    @endif
                </section>
            @endif

            <section class="site-card p-6">
                <h2 class="site-h2">Get in touch</h2>
                <ul class="mt-3 space-y-2">
                    @if ($club->email)
                        <li><a href="mailto:{{ $club->email }}" class="inline-flex items-center gap-2 break-all hover:text-court hover:underline"><x-icon name="mail" class="size-4 shrink-0 text-line-strong" />{{ $club->email }}</a></li>
                    @endif
                    @if ($club->phone)
                        <li><a href="tel:{{ preg_replace('/[^\d+]/', '', $club->phone) }}" class="inline-flex items-center gap-2 hover:text-court hover:underline"><x-icon name="phone" class="size-4 text-line-strong" />{{ $club->phone }}</a></li>
                    @endif
                    @if ($club->website)
                        <li><a href="{{ $club->website }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 break-all hover:text-court hover:underline"><x-icon name="globe" class="size-4 shrink-0 text-line-strong" />{{ $short($club->website) }}</a></li>
                    @endif
                    @foreach ($socials as $key => $label)
                        <li><a href="{{ $club->getAttribute($key) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 hover:text-court hover:underline"><x-icon name="external-link" class="size-4 text-line-strong" />{{ $label }}</a></li>
                    @endforeach
                    @unless ($club->email || $club->phone || $club->website || $socials)
                        <li class="text-muted">Contact details are coming soon.</li>
                    @endunless
                </ul>
            </section>
        </aside>
    </div>
</x-site>
