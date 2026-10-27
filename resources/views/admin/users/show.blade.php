@php
    $isMe = $user->is(auth()->user());
    $signIn = collect([$user->password ? 'Password' : null, $user->google_id ? 'Google' : null])->filter();
@endphp

<x-admin.layout :title="$user->name" :breadcrumb="['Users', $user->name]">
    <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Users
    </a>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3">
            @if ($user->avatar)
                <img src="{{ $user->avatar }}" alt="" class="size-12 shrink-0 rounded-xl object-cover" referrerpolicy="no-referrer">
            @else
                <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-linear-to-br from-brand-300 to-brand-500 font-bold text-ink-950 shadow-[0_6px_16px_-6px_rgb(238_176_8/0.7)]">{{ $user->initials() }}</span>
            @endif
            <div class="min-w-0">
                <h1 class="truncate text-xl font-bold tracking-tight">
                    {{ $user->name }}
                    @if ($isMe)
                        <span class="ml-1 align-middle text-sm font-normal text-slate-500">(you)</span>
                    @endif
                </h1>
                <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.users.edit', $user) }}" class="btn-gold">
                <x-icon name="pencil" class="size-4" /> Edit
            </a>
            @unless ($isMe)
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" @submit="confirm(@js("Delete {$user->name} ({$user->email})? They won’t be able to sign in.")) || $event.preventDefault()">
                    @csrf
                    @method('DELETE')
                    <button class="btn-outline text-red-600 hover:bg-red-50">
                        <x-icon name="trash" class="size-4" /> Delete
                    </button>
                </form>
            @endunless
        </div>
    </div>

    <div class="mt-4 grid gap-3 lg:grid-cols-3">
        {{-- Account --}}
        <section class="card p-4 sm:p-5">
            <h2 class="text-sm font-semibold">Account</h2>
            <dl class="mt-3 divide-y divide-slate-100 text-sm">
                <div class="flex justify-between gap-3 py-2">
                    <dt class="text-slate-500">Joined</dt>
                    <dd title="{{ $user->created_at?->format('j M Y, g:i A') }}">{{ $user->created_at?->format('j M Y') }}</dd>
                </div>
                <div class="flex justify-between gap-3 py-2">
                    <dt class="text-slate-500">Last updated</dt>
                    <dd>{{ $user->updated_at?->diffForHumans() }}</dd>
                </div>
                <div class="flex justify-between gap-3 py-2">
                    <dt class="text-slate-500">Email</dt>
                    <dd>
                        @if ($user->email_verified_at)
                            <span class="inline-flex items-center gap-1 text-emerald-600"><x-icon name="check" class="size-3.5" stroke-width="2.5" /> Verified</span>
                        @else
                            <span class="text-slate-500">Not verified</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-3 py-2">
                    <dt class="text-slate-500">Signs in with</dt>
                    <dd>{{ $signIn->implode(' and ') ?: '—' }}</dd>
                </div>
            </dl>
        </section>

        {{-- Roles and what they allow --}}
        <section class="card p-4 sm:p-5 lg:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold">Roles</h2>
                <a href="{{ route('admin.users.edit', $user) }}" class="text-sm font-medium text-brand-600 hover:underline">Change roles</a>
            </div>

            <div class="mt-3 flex flex-wrap gap-1.5">
                @forelse ($user->roles as $role)
                    <a href="{{ route('admin.roles.edit', $role) }}" class="{{ $role->isProtected() ? 'badge-gold' : 'badge-muted' }} hover:brightness-95">{{ $role->name }}</a>
                @empty
                    <span class="text-sm text-slate-500">No role yet, so they can’t do anything in the admin.</span>
                @endforelse
            </div>

            @if ($permissions->isNotEmpty())
                <h3 class="mt-5 text-sm font-semibold">What they can do</h3>
                <div class="mt-2 grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($permissions as $module => $modulePermissions)
                        <div>
                            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">{{ $module }}</p>
                            <ul class="mt-1 space-y-0.5 text-sm">
                                @foreach ($modulePermissions as $permission)
                                    <li class="flex items-center gap-1.5" title="{{ $permission->name }}">
                                        <x-icon name="check" class="size-3.5 text-emerald-600" stroke-width="2.5" />
                                        {{ $permission->description ?: $permission->name }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    {{-- Recent activity --}}
    <section class="card mt-3 p-4 sm:p-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold">Recent activity</h2>
            <a href="{{ route('admin.activity', ['actor' => $user->name, 'range' => 'all']) }}" class="text-sm font-medium text-brand-600 hover:underline">View all</a>
        </div>

        <ul class="mt-2 divide-y divide-slate-100">
            @forelse ($activity as $event)
                <li class="flex items-center gap-3 py-2 text-sm">
                    <span @class(['size-2 shrink-0 rounded-full', 'bg-emerald-500' => $event->succeeded, 'bg-red-500' => ! $event->succeeded])></span>
                    <span class="min-w-0 flex-1 truncate">
                        <span class="font-medium">{{ $event->actor_name ?? 'Someone' }}</span>
                        {{ lcfirst($event->description) }}
                    </span>
                    <a href="{{ route('admin.activity', ['event' => $event->id, 'range' => 'all']) }}" class="shrink-0 text-xs text-slate-500 hover:text-ink-950" title="{{ $event->created_at->format('j M Y, g:i A') }}">
                        {{ $event->created_at->diffForHumans() }}
                    </a>
                </li>
            @empty
                <li class="py-4 text-center text-sm text-slate-500">No activity yet.</li>
            @endforelse
        </ul>
    </section>
</x-admin.layout>
