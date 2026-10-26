<x-admin.layout title="Overview" :breadcrumb="['Dashboard', 'Overview']">
    <x-admin.page-header :title="$greeting.', '.$firstName" :subtitle="'Here’s what’s happening in '.config('app.name').' today, '.now()->format('l j F').'.'" />

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('admin.users.index') }}" class="card block p-4 transition hover:border-brand-300">
            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">Users</p>
                <x-icon name="users" class="text-brand-500" />
            </div>
            <p class="mt-1 text-2xl font-bold tracking-tight">{{ number_format($stats['users']) }}</p>
            <p class="mt-0.5 text-xs text-brand-600">{{ $stats['newUsers'] }} new this month</p>
        </a>
        <a href="{{ route('admin.roles.index') }}" class="card block p-4 transition hover:border-brand-300">
            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">Roles</p>
                <x-icon name="shield-check" class="text-brand-500" />
            </div>
            <p class="mt-1 text-2xl font-bold tracking-tight">{{ $stats['roles'] }}</p>
            <p class="mt-0.5 text-xs text-brand-600">Assignable to users</p>
        </a>
        <a href="{{ route('admin.permissions.index') }}" class="card block p-4 transition hover:border-brand-300">
            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">Permissions</p>
                <x-icon name="key" class="text-brand-500" />
            </div>
            <p class="mt-1 text-2xl font-bold tracking-tight">{{ $stats['permissions'] }}</p>
            <p class="mt-0.5 text-xs text-brand-600">Across {{ $stats['modules'] }} {{ str('module')->plural($stats['modules']) }}</p>
        </a>
        <a href="{{ route('admin.activity', ['range' => '1']) }}" class="card block p-4 transition hover:border-brand-300">
            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-500">Activity today</p>
                <x-icon name="chart-column" class="text-brand-500" />
            </div>
            <p class="mt-1 text-2xl font-bold tracking-tight">{{ number_format($stats['eventsToday']) }}</p>
            <p @class(['mt-0.5 text-xs', 'text-red-600' => $stats['failedToday'], 'text-brand-600' => ! $stats['failedToday']])>
                {{ $stats['failedToday'] ? $stats['failedToday'].' failed' : 'Nothing failed' }}
            </p>
        </a>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
        {{-- Recent activity --}}
        <section class="card min-w-0 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold">Recent activity</h2>
                <a href="{{ route('admin.activity') }}" class="text-sm font-medium text-brand-600 hover:underline">View activity log</a>
            </div>

            <ul class="mt-3 divide-y divide-slate-100">
                @forelse ($recentActivity as $event)
                    <li class="flex items-start gap-3 py-2.5">
                        @if ($event->user)
                            <x-admin.avatar :initials="$event->user->initials()" :tone="$event->succeeded ? 'gray' : 'gold'" />
                        @else
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500"><x-icon name="users" class="size-4" /></span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-sm">
                                <span class="font-medium">{{ $event->actor_name ?? 'Someone' }}</span>
                                <span @class(['text-red-600' => ! $event->succeeded])>{{ lcfirst($event->description) }}</span>
                            </p>
                            <p class="text-xs text-slate-500" title="{{ $event->created_at->format('j M Y, g:i A') }}">{{ $event->created_at->diffForHumans() }} · {{ $event->module() }}</p>
                        </div>
                        <a href="{{ route('admin.activity', ['event' => $event->id, 'range' => 'all']) }}" class="shrink-0 text-xs font-medium text-slate-500 hover:text-ink-950">Details</a>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-slate-500">No activity yet. Sign-ins and changes to users, roles and permissions will show up here.</li>
                @endforelse
            </ul>
        </section>

        <div class="space-y-3">
            {{-- Newest users --}}
            <section class="card min-w-0 p-4 sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold">Newest users</h2>
                    <a href="{{ route('admin.users.create') }}" class="text-sm font-medium text-brand-600 hover:underline">Add user</a>
                </div>

                <ul class="mt-3 divide-y divide-slate-100">
                    @forelse ($newestUsers as $user)
                        <li class="flex items-center gap-3 py-2">
                            <x-admin.avatar :initials="$user->initials()" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.users.edit', $user) }}" class="block truncate text-sm font-medium hover:underline">{{ $user->name }}</a>
                                <p class="truncate text-xs text-slate-500">{{ $user->getRoleNames()->implode(', ') ?: 'No role' }}</p>
                            </div>
                            <span class="shrink-0 text-xs text-slate-500">{{ $user->created_at?->diffForHumans(short: true) }}</span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-slate-500">No users yet.</li>
                    @endforelse
                </ul>
            </section>

            {{-- Roles --}}
            <section class="card min-w-0 p-4 sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold">People per role</h2>
                    <a href="{{ route('admin.roles.index') }}" class="text-sm font-medium text-brand-600 hover:underline">Manage roles</a>
                </div>

                @php($most = max(1, $roles->max('users_count')))
                <ul class="mt-3 space-y-2.5">
                    @forelse ($roles as $role)
                        <li>
                            <div class="flex items-center justify-between text-sm">
                                <a href="{{ route('admin.roles.edit', $role) }}" class="font-medium hover:underline">{{ $role->name }}</a>
                                <span class="tabular-nums text-slate-500">{{ $role->users_count }}</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-slate-100">
                                <div class="h-1.5 rounded-full bg-brand-400" style="width: {{ round($role->users_count / $most * 100) }}%"></div>
                            </div>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-slate-500">No roles yet.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-admin.layout>
