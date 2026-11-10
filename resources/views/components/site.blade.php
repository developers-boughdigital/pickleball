{{-- Layout for the public site, in the style of design/Dink Circle City page and Coach index.html. --}}
{{--
    Every public page gets: a title and description (editable in Admin › SEO),
    canonical, robots, Open Graph and Twitter tags, and one JSON-LD graph with
    the Organization, WebSite, WebPage, breadcrumbs and the page's own $schema.
--}}
@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'noindex' => false,
    'image' => null,
    'pageType' => 'WebPage',
    'breadcrumbs' => [],
    'schema' => [],
    'head' => null,
])

@php($seo = new \App\Support\Seo\Seo($title, $description, $canonical, $noindex, $image, $pageType, $breadcrumbs, $schema))

<!DOCTYPE html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo->fullTitle }}</title>
    <meta name="description" content="{{ $seo->description }}">
    <link rel="canonical" href="{{ $seo->canonical }}">
    <meta name="robots" content="{{ $seo->noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large' }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="en_AU">
    <meta property="og:title" content="{{ $seo->title }}">
    <meta property="og:description" content="{{ $seo->description }}">
    <meta property="og:url" content="{{ $seo->canonical }}">
    <meta property="og:image" content="{{ $seo->image }}">
    <meta property="og:image:alt" content="{{ config('app.name') }}: pickleball clubs, courts and coaches across Australia">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo->title }}">
    <meta name="twitter:description" content="{{ $seo->description }}">
    <meta name="twitter:image" content="{{ $seo->image }}">

    <script type="application/ld+json">{!! $seo->jsonLd() !!}</script>
    {{ $head }}
    @if (config('app.logo'))
        <link rel="icon" href="{{ asset(config('app.logo')) }}">
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=atkinson-hyperlegible:400,700|barlow-semi-condensed:600,700&display=swap" rel="stylesheet">
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
