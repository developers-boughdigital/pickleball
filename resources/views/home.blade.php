{{-- Home page. Kept simple for now; the explore pages hold the content. --}}
<x-site description="Pickleball clubs, courts and coaches across Australia.">
    <section class="border-b border-line bg-white">
        <div class="site-wrap flex flex-wrap items-end gap-10 py-12 sm:py-16">
            <div class="min-w-0 flex-[1_1_520px]">
                <p class="font-bold text-gum-text">Pickleball across Australia</p>
                <h1 class="mt-2 font-display text-[40px] leading-[44px] font-bold sm:text-5xl sm:leading-[52px]">Your home for pickleball.</h1>
                <p class="mt-4 max-w-[640px] text-lg leading-7 text-muted">Find a club to play with, a court near you, or a coach to lift your game.</p>
            </div>

            <form method="GET" action="{{ route('explore.clubs') }}" class="flex min-w-0 flex-[1_1_380px] flex-col gap-3 rounded-[20px] bg-white p-6 shadow-[0_12px_32px_rgb(15_27_45/0.14)]">
                <label for="q" class="site-h3">Find a game near you</label>
                <input id="q" name="q" type="search" placeholder="Suburb, postcode or club name" class="site-field">
                <button class="site-btn-ball min-h-14 text-lg">Search</button>
            </form>
        </div>
    </section>

    <section class="site-wrap py-16">
        <div class="grid grid-cols-[repeat(auto-fill,minmax(min(260px,100%),1fr))] gap-6">
            @foreach ([
                ['explore', 'map-pin', 'Play near you', 'Courts, clubs and sessions by state and suburb.'],
                ['explore.clubs', 'users', 'Clubs', 'Social play, coaching and competition for every level.'],
                ['explore.venues', 'layout-grid', 'Courts and venues', 'Indoor halls and outdoor courts, and how to book.'],
                ['explore.coaches', 'user', 'Coaches', 'Private lessons, small groups and clinics.'],
            ] as [$route, $icon, $label, $text])
                <a href="{{ route($route) }}" class="site-card flex flex-col gap-2 p-6 text-navy no-underline transition hover:shadow-[0_12px_32px_rgb(15_27_45/0.14)]">
                    <span class="grid size-12 place-items-center rounded-xl bg-court-soft text-court"><x-icon :name="$icon" class="size-6" /></span>
                    <span class="site-h3 mt-2 text-court">{{ $label }}</span>
                    <span class="text-muted">{{ $text }}</span>
                </a>
            @endforeach
        </div>
    </section>
</x-site>
