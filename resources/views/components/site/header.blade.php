{{-- The public site header. Every public page uses this one, so the menu is the same everywhere. --}}
@php
    $nav = [
        'home' => ['Home', request()->routeIs('home')],
        'play' => ['Play', request()->routeIs('play', 'play.*')],
        'explore.clubs' => ['Clubs', request()->routeIs('explore.clubs', 'explore.club')],
        'explore.venues' => ['Venues', request()->routeIs('explore.venues', 'explore.venue')],
        'explore.coaches' => ['Coaches', request()->routeIs('explore.coaches')],
    ];
    preg_match('/^(.*\S)(\s+\S+|[A-Z][^A-Z\s]*)$/u', config('app.name'), $name);
@endphp

<header class="border-b border-line bg-white" x-data="{ open: false }" @spa-navigated.window="open = false">
    <div class="site-wrap flex min-h-20 flex-wrap items-center gap-x-6 gap-y-2 py-3">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 no-underline" aria-label="{{ config('app.name') }} home">
            @if (config('app.logo'))
                <img src="{{ asset(config('app.logo')) }}" alt="" class="size-10 rounded-xl object-contain">
            @else
                <svg width="56" height="40" viewBox="0 0 56 40" aria-hidden="true">
                    <path d="M6 34 Q28 -6 50 34" fill="none" stroke="#5DC293" stroke-width="3" stroke-linecap="round" stroke-dasharray="0.1 6" />
                    <line x1="28" y1="22" x2="28" y2="35" stroke="#0F1B2D" stroke-width="3" stroke-linecap="round" />
                    <line x1="3" y1="36" x2="53" y2="36" stroke="#0F1B2D" stroke-width="3" stroke-linecap="round" />
                    <circle cx="28" cy="11" r="6" fill="#FFC93C" />
                </svg>
            @endif
            <span class="flex flex-col font-display text-xl leading-[18px] font-bold">
                @if ($name)
                    <span class="text-court">{{ $name[1] }}</span><span class="text-navy">{{ trim($name[2]) }}</span>
                @else
                    <span class="text-court">{{ config('app.name') }}</span>
                @endif
            </span>
        </a>

        <button type="button" class="ml-auto grid size-11 place-items-center rounded-xl text-navy sm:hidden" @click="open = ! open" :aria-expanded="open" aria-label="Menu">
            <x-icon name="menu" class="size-6" />
        </button>

        <nav id="site-nav" data-swap aria-label="Main" class="hidden w-full flex-col gap-1 sm:flex sm:w-auto sm:flex-1 sm:flex-row sm:flex-wrap" :class="open && '!flex'">
            @foreach ($nav as $route => [$label, $active])
                <a href="{{ route($route) }}" @class([
                    'rounded-xl px-3.5 py-2.5 font-bold no-underline',
                    'bg-court-soft text-court-deep' => $active,
                    'text-navy hover:text-court' => ! $active,
                ]) @if ($active) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div id="site-account" data-swap class="hidden gap-2 sm:flex" :class="open && '!flex'">
            @auth
                <a href="{{ route('dashboard') }}" data-reload class="site-btn-outline">Admin</a>
            @else
                <a href="{{ route('login') }}" @class(['site-btn-outline', 'site-chip-on' => request()->routeIs('login')])>Log in</a>
                <a href="{{ route('register') }}" class="site-btn-primary">Register</a>
            @endauth
        </div>
    </div>
</header>
