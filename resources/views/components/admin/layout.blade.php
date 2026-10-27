@props(['title', 'breadcrumb' => ['Admin', null]])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    <script>
        // Apply the saved sidebar state before first paint to avoid a flash.
        try {
            if (localStorage.getItem('sidebar-collapsed') === '1') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    x-data="adminLayout"
    @keydown.escape.window="sidebarOpen = false"
    @spa-navigated.window="sidebarOpen = false"
    class="min-h-screen bg-[#fafafa] font-sans text-ink-950 antialiased"
>
    {{-- Page loading bar --}}
    <div x-show="$store.nav.loading" x-cloak class="fixed inset-x-0 top-0 z-50 h-0.5 overflow-hidden bg-brand-100">
        <div class="h-full w-1/3 animate-progress bg-brand-500"></div>
    </div>

    <x-admin.sidebar />

    <div class="transition-[padding] duration-300 ease-in-out md:pl-24 lg:pl-60 lg:collapsed:pl-20">
        <x-admin.topbar :breadcrumb="$breadcrumb" />

        <main id="content" data-swap class="px-4 py-4 sm:px-5 lg:px-6 lg:py-5">
            @if (session('status'))
                <div class="mb-3 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-700">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
