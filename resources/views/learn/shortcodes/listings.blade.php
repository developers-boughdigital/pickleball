{{-- [clubs …], [venues …], [club …] and [venue …]: cards that link into the directory. --}}
<div class="not-prose my-6 grid grid-cols-[repeat(auto-fill,minmax(min(220px,100%),1fr))] gap-4">
    @foreach ($listings as $listing)
        <a href="{{ route($kind === 'club' ? 'explore.club' : 'explore.venue', $listing) }}" class="site-card flex flex-col gap-1 p-5 text-navy no-underline transition hover:shadow-[0_12px_32px_rgb(15_27_45/0.14)]">
            <span class="font-bold text-gum-text">{{ $kind === 'club' ? 'Club' : 'Venue' }}</span>
            <span class="site-h3 text-court">{{ $listing->name }}</span>
            <span class="text-muted">{{ collect([$listing->suburb, $listing->state])->filter()->implode(', ') ?: 'Australia' }}</span>
        </a>
    @endforeach
</div>
