@php
    // Each item: label, compact label + icon for the tablet rail, route (null = not built yet),
    // route patterns that mark it active, optional children, and an optional role or permission needed to see it.
    $nav = collect([
        ['label' => 'Overview', 'short' => 'Overview', 'icon' => 'layout-grid', 'rail' => 'layout-grid', 'route' => 'dashboard', 'active' => ['dashboard']],
        ['label' => 'User & Security', 'short' => 'Security', 'icon' => 'shield-check', 'rail' => 'users', 'route' => 'admin.users.index', 'active' => ['admin.users.*', 'admin.roles.*', 'admin.permissions.*', 'admin.activity'], 'children' => [
            ['label' => 'Users', 'route' => 'admin.users.index', 'active' => ['admin.users.*']],
            ['label' => 'Roles & Permissions', 'route' => 'admin.roles.index', 'active' => ['admin.roles.*', 'admin.permissions.*']],
            ['label' => 'Activity Log', 'route' => 'admin.activity'],
        ]],
        ['label' => 'System Settings', 'short' => 'Settings', 'icon' => 'sliders', 'rail' => 'settings', 'route' => 'admin.settings', 'active' => ['admin.settings*'], 'can' => 'settings.view'],
        ['label' => 'Database', 'short' => 'Database', 'icon' => 'database', 'rail' => 'database', 'route' => 'admin.database', 'active' => ['admin.database*'], 'role' => \App\Models\Role::SUPER_ADMIN],
    ])->filter(fn (array $item) => (! isset($item['role']) || auth()->user()?->hasRole($item['role']))
        && (! isset($item['can']) || auth()->user()?->can($item['can'])));

    $href = fn (array $item) => isset($item['route']) ? route($item['route']) : '#';
    $isActive = fn (array $item) => isset($item['active']) && request()->routeIs(...$item['active']);
@endphp

{{-- Mobile overlay --}}
<div
    x-show="sidebarOpen"
    x-transition.opacity
    x-cloak
    @click="sidebarOpen = false"
    class="fixed inset-0 z-40 bg-ink-950/60 lg:hidden"
></div>

{{--
    Full sidebar: a drawer below lg, fixed on desktop. When collapsed on desktop it
    narrows to icons only; labels fade and the sub-menu folds away.
--}}
<aside
    :class="{ '-translate-x-full': ! sidebarOpen }"
    class="fixed inset-y-0 left-0 z-50 flex w-60 -translate-x-full flex-col overflow-hidden bg-ink-950 text-slate-200 transition-[translate,width] duration-300 ease-in-out lg:translate-x-0 lg:collapsed:w-20"
>
    <div class="flex h-14 shrink-0 items-center justify-between border-b border-white/15 px-5">
        <a href="{{ route('dashboard') }}" class="group flex min-w-0 items-center gap-3" title="{{ config('app.name') }}">
            <x-admin.brand-mark class="size-8 transition group-hover:rotate-12" />
            <x-admin.brand-name class="truncate text-lg leading-none font-extrabold tracking-tight whitespace-nowrap text-white transition-opacity duration-200 lg:collapsed:opacity-0" />
        </a>
        <button type="button" @click="sidebarOpen = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-white/10 lg:hidden" aria-label="Close menu">
            <x-icon name="x" />
        </button>
    </div>

    <nav id="sidebar-nav" data-swap class="flex-1 space-y-1 overflow-x-hidden overflow-y-auto px-3.5 py-3">
        @foreach ($nav as $item)
            @php($active = $isActive($item))
            <a href="{{ $href($item) }}" title="{{ $item['label'] }}" @class([
                'flex items-center gap-3 rounded-lg px-4 py-2 text-sm transition',
                'nav-active' => $active,
                'text-slate-200 hover:bg-white/5' => ! $active,
            ])>
                <x-icon :name="$item['icon']" :class="$active ? 'text-ink-950' : 'text-slate-400'" />
                <span class="whitespace-nowrap transition-opacity duration-200 lg:collapsed:opacity-0">{{ $item['label'] }}</span>
            </a>

            @isset($item['children'])
                {{-- grid-rows 1fr -> 0fr lets the sub-menu fold to zero height smoothly --}}
                <div class="grid grid-rows-[1fr] transition-[grid-template-rows,opacity] duration-300 ease-in-out lg:collapsed:grid-rows-[0fr] lg:collapsed:opacity-0">
                    <div class="overflow-hidden">
                        <div class="ml-8 space-y-1 border-l border-white/20 py-1 pl-3">
                            @foreach ($item['children'] as $child)
                                @php($childActive = request()->routeIs(...($child['active'] ?? [$child['route']])))
                                <a href="{{ route($child['route']) }}" @class([
                                    'block rounded-lg px-3 py-1.5 text-sm whitespace-nowrap transition',
                                    'nav-active' => $childActive,
                                    'text-slate-300 hover:bg-white/5 hover:text-white' => ! $childActive,
                                ])>{{ $child['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endisset
        @endforeach
    </nav>
</aside>

{{-- Compact rail: tablet only --}}
<aside class="fixed inset-y-0 left-0 z-30 hidden w-24 flex-col bg-ink-950 text-slate-200 md:flex lg:hidden">
    <a href="{{ route('dashboard') }}" class="flex h-14 shrink-0 flex-col items-center justify-center gap-1 border-b border-white/15">
        <x-admin.brand-mark class="size-7" />
        <x-admin.brand-name accent="text-brand-400" class="max-w-full truncate px-1 text-xs leading-none font-extrabold tracking-tight text-white" />
    </a>

    <nav id="rail-nav" data-swap class="flex-1 space-y-1 overflow-y-auto px-2 py-3">
        @foreach ($nav as $item)
            @php($active = $isActive($item))
            <a href="{{ $href($item) }}" title="{{ $item['label'] }}" @class([
                'flex flex-col items-center gap-1.5 rounded-xl px-1 py-2 text-center text-[11px] leading-tight font-medium transition',
                'nav-active' => $active,
                'text-slate-200 hover:bg-white/5' => ! $active,
            ])>
                <x-icon :name="$item['rail']" :class="$active ? 'text-ink-950' : 'text-slate-300'" />
                {{ $item['short'] }}
            </a>

            @isset($item['children'])
                <div class="ml-3 space-y-0.5 border-l border-white/20 py-1 pl-1.5">
                    @foreach ($item['children'] as $child)
                        @php($childActive = request()->routeIs(...($child['active'] ?? [$child['route']])))
                        <a href="{{ route($child['route']) }}" @class([
                            'block rounded-md px-1.5 py-1.5 text-[10px] leading-tight transition',
                            'nav-active' => $childActive,
                            'text-slate-300 hover:text-white' => ! $childActive,
                        ])>{{ $child['label'] }}</a>
                    @endforeach
                </div>
            @endisset
        @endforeach
    </nav>
</aside>
