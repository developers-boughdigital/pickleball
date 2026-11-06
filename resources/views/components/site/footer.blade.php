{{-- The public site footer, shared by every public page. --}}
@php
    preg_match('/^(.*\S)(\s+\S+|[A-Z][^A-Z\s]*)$/u', config('app.name'), $name);
@endphp

<footer class="bg-navy text-[#eef2f8]">
    <div class="site-wrap grid gap-8 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="font-display text-[22px] leading-6 font-bold">
                @if ($name)
                    <span class="text-ball">{{ $name[1] }}</span> {{ trim($name[2]) }}
                @else
                    <span class="text-ball">{{ config('app.name') }}</span>
                @endif
            </p>
            <p class="mt-3 text-[#a9b6cb]">Your home for pickleball. Clubs, courts and coaches across Australia.</p>
        </div>
        <nav aria-label="Play by state" class="flex flex-col gap-2">
            <span class="font-bold">Play</span>
            @foreach (['VIC', 'NSW', 'QLD'] as $state)
                <a href="{{ route('explore', ['state' => $state]) }}" class="text-[#eef2f8] hover:text-ball">{{ \App\Models\Venue::STATE_NAMES[$state] }}</a>
            @endforeach
            <a href="{{ route('explore') }}" class="text-[#eef2f8] hover:text-ball">All states</a>
        </nav>
        <nav aria-label="Find" class="flex flex-col gap-2">
            <span class="font-bold">Find</span>
            <a href="{{ route('explore.clubs') }}" class="text-[#eef2f8] hover:text-ball">Clubs</a>
            <a href="{{ route('explore.venues') }}" class="text-[#eef2f8] hover:text-ball">Courts and venues</a>
            <a href="{{ route('explore.coaches') }}" class="text-[#eef2f8] hover:text-ball">Coaches</a>
        </nav>
        <nav aria-label="For clubs and coaches" class="flex flex-col gap-2">
            <span class="font-bold">Clubs and coaches</span>
            <a href="{{ route('login') }}" class="text-[#eef2f8] hover:text-ball">Claim your club page</a>
            <a href="{{ route('explore.coaches') }}#list-yourself" class="text-[#eef2f8] hover:text-ball">List yourself as a coach</a>
            <span class="text-[#a9b6cb]">&copy; {{ date('Y') }} {{ config('app.name') }}</span>
        </nav>
    </div>
</footer>
