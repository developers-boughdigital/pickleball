{{-- Layout for the public site, in the style of design/Dink Circle City page and Coach index.html. --}}
@props(['title' => null, 'description' => null])

<!DOCTYPE html>
<html lang="en-AU">
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
    <link href="https://fonts.bunny.net/css?family=atkinson-hyperlegible:400,700|barlow-semi-condensed:600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-layout="site" class="flex min-h-screen flex-col bg-surface font-read text-base text-navy antialiased">
    {{-- Page loading bar --}}
    <div x-data x-show="$store.nav.loading" x-cloak class="fixed inset-x-0 top-0 z-50 h-1 overflow-hidden bg-court-soft">
        <div class="h-full w-1/3 animate-progress bg-court"></div>
    </div>

    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2">Skip to content</a>

    <x-site.header />

    <main id="main" data-swap class="flex-1">
        {{ $slot }}
    </main>

    <x-site.footer />
</body>
</html>
