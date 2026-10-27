@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? "$title · " : '' }}{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <nav class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <a href="{{ route('home') }}" class="text-lg font-semibold text-emerald-600">🏓 {{ config('app.name') }}</a>

            <div class="flex items-center gap-4 text-sm font-medium">
                @auth
                    <span class="text-slate-600">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-100">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hover:text-emerald-600">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-white hover:bg-emerald-700">Register</a>
                @endauth
            </div>
        </nav>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-6 sm:py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
