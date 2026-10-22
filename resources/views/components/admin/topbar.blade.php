@props(['breadcrumb'])

@php($user = auth()->user())

<header class="sticky top-0 z-20 flex h-20 items-center gap-3 border-b border-white/15 bg-ink-950 px-4 text-white sm:px-6 lg:px-12">
    <button
        type="button"
        @click="toggleSidebar()"
        :aria-expanded="(sidebarOpen || ! collapsed).toString()"
        class="-ml-2 rounded-lg p-2 text-slate-300 transition hover:bg-white/10 hover:text-white"
        aria-label="Toggle menu"
    >
        <x-icon name="menu" class="size-6" />
    </button>

    <nav id="breadcrumb" data-swap class="flex min-w-0 items-center gap-3 text-[15px]" aria-label="Breadcrumb">
        <span class="hidden text-slate-400 sm:inline">{{ $breadcrumb[0] }}</span>
        <x-icon name="chevron-right" class="hidden size-4 text-slate-500 sm:block" />
        <span class="truncate font-medium text-white">{{ $breadcrumb[1] }}</span>
    </nav>

    <div class="ml-auto flex items-center gap-1 sm:gap-4">
        <label
            class="relative hidden md:block"
            @keydown.window.meta.k.prevent="$refs.search.focus()"
            @keydown.window.ctrl.k.prevent="$refs.search.focus()"
        >
            <span class="sr-only">Search</span>
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-400" />
            <input
                type="search"
                x-ref="search"
                placeholder="Search admin"
                class="h-11 w-52 rounded-lg border border-white/15 bg-white/5 pr-14 pl-11 text-[15px] text-white placeholder:text-slate-400 focus:border-brand-400 focus:bg-white/10 focus:ring-2 focus:ring-brand-500/30 focus:outline-none lg:w-72 xl:w-80"
            >
            <kbd class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 rounded-md border border-white/15 px-1.5 py-0.5 font-sans text-xs text-slate-400">⌘K</kbd>
        </label>

        <button type="button" class="relative rounded-lg p-2 text-slate-300 hover:bg-white/10 hover:text-white" aria-label="Notifications">
            <x-icon name="bell" class="size-6" />
            <span class="absolute top-1.5 right-2 size-2 rounded-full bg-brand-500 ring-2 ring-ink-950"></span>
        </button>

        <span class="hidden h-10 w-px bg-white/15 sm:block"></span>

        <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative text-ink-950">
            <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-label="Account menu" class="flex items-center gap-3 rounded-full p-1 pr-2 hover:bg-white/10">
                <span class="grid size-10 place-items-center rounded-full bg-brand-500 text-sm font-semibold text-ink-950">{{ $user->initials() }}</span>
                <span class="hidden text-[15px] font-medium text-white lg:block">{{ $user->name }}</span>
                <x-icon name="chevron-down" class="size-4 text-slate-400 transition" ::class="open && 'rotate-180'" />
            </button>

            <div
                x-show="open"
                x-transition.origin.top.right
                x-cloak
                class="card absolute right-0 mt-2 w-60 p-2"
            >
                <div class="border-b border-slate-100 px-3 pt-1 pb-3">
                    <p class="truncate text-sm font-semibold">{{ $user->name }}</p>
                    <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="pt-2">
                    @csrf
                    <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                        <x-icon name="log-out" class="size-4" />
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
