{{-- Layout for the public site (explore pages). --}}
@props(['title' => null, 'description' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? "$title · " : '' }}{{ config('app.name') }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    @if (config('app.logo'))
        <link rel="icon" href="{{ asset(config('app.logo')) }}">
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-slate-50 text-ink-950 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2 focus:text-sm">Skip to content</a>

    <header class="bg-ink-950 text-white">
        <nav class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
            <a href="{{ route('explore') }}" class="flex items-center gap-2 text-lg font-bold">
                <x-admin.brand-mark :custom="config('app.logo')" class="size-8" />
                <x-admin.brand-name />
            </a>

            <div class="flex items-center gap-1 text-sm font-medium sm:gap-3">
                <a href="{{ route('explore') }}" @class(['rounded-lg px-3 py-1.5 transition hover:bg-white/10', 'bg-white/10' => request()->routeIs('explore*')])>Explore</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-1.5 text-white/80 transition hover:bg-white/10 hover:text-white">Admin</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-3 py-1.5 text-white/80 transition hover:bg-white/10 hover:text-white">Log in</a>
                @endauth
            </div>
        </nav>
    </header>

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Pickleball clubs and courts across Australia.</p>
            <p>Run a club or venue? <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline">Help keep your listing up to date</a>.</p>
        </div>
    </footer>
</body>
</html>
