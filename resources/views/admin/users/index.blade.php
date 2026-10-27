<x-admin.layout title="Users" :breadcrumb="['User & Security', 'Users']">
    <x-admin.page-header title="Users" subtitle="Everyone who can sign in, and the roles they hold.">
        <a href="{{ route('admin.users.create') }}" class="btn-gold">
            <x-icon name="user-plus" />
            Add user
        </a>
    </x-admin.page-header>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Users" :value="number_format($stats['total'])" note="Accounts that can sign in" />
        <x-admin.stat label="New this month" :value="number_format($stats['newThisMonth'])" :note="now()->format('F Y')" />
        <x-admin.stat label="With a role" :value="number_format($stats['withRole'])" :note="number_format($stats['total'] - $stats['withRole']).' without one'" />
        <x-admin.stat label="Roles" :value="$stats['roles']" note="Available to assign" />
    </div>

    <form method="GET" class="mt-4 flex flex-col gap-3 md:flex-row">
        <x-admin.search :value="$filters['search']" placeholder="Search by name or email" class="md:flex-1" />
        <x-admin.select name="role" :options="$roleOptions" :selected="$filters['role']" placeholder="All roles" class="md:w-56" />
    </form>

    <div class="card mt-3 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Name</th>
                        <th class="px-4 py-2.5 font-medium">Email</th>
                        <th class="px-4 py-2.5 font-medium">Roles</th>
                        <th class="px-4 py-2.5 font-medium">Joined</th>
                        <th class="px-4 py-2.5 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 border-t border-slate-200">
                    @forelse ($users as $user)
                        @php($isMe = $user->is(auth()->user()))
                        <tr class="transition hover:bg-slate-50/60">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-3">
                                    <x-admin.avatar :initials="$user->initials()" :tone="$isMe ? 'solid' : 'gray'" />
                                    <div>
                                        <a href="{{ route('admin.users.show', $user) }}" class="font-medium hover:underline">{{ $user->name }}</a>
                                        @if ($isMe)
                                            <span class="ml-1 text-xs text-slate-500">(you)</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $user->email }}</td>
                            <td class="px-4 py-2.5">
                                @forelse ($user->roles as $role)
                                    <span class="{{ $role->isProtected() ? 'badge-gold' : 'badge-muted' }} mr-1">{{ $role->name }}</span>
                                @empty
                                    <span class="text-slate-400">No role</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-2.5 text-slate-500" title="{{ $user->created_at?->format('j M Y, g:i A') }}">{{ $user->created_at?->format('j M Y') }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('admin.users.show', $user) }}" class="icon-btn-view" title="View {{ $user->name }}">
                                        <x-icon name="eye" class="size-4" />
                                        <span class="sr-only">View {{ $user->name }}</span>
                                    </a>
                                    <a href="{{ route('admin.users.edit', $user) }}" class="icon-btn-edit" title="Edit {{ $user->name }}">
                                        <x-icon name="pencil" class="size-4" />
                                        <span class="sr-only">Edit {{ $user->name }}</span>
                                    </a>
                                    @if ($isMe)
                                        <span class="icon-btn-locked" title="You can’t delete your own account">
                                            <x-icon name="trash" class="size-4" />
                                            <span class="sr-only">You can’t delete your own account</span>
                                        </span>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" @submit="confirm(@js("Delete {$user->name} ({$user->email})? They won’t be able to sign in.")) || $event.preventDefault()">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn-delete" title="Delete {{ $user->name }}">
                                                <x-icon name="trash" class="size-4" />
                                                <span class="sr-only">Delete {{ $user->name }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                                @if (array_filter($filters))
                                    No users match your filters.
                                @else
                                    No users yet. <a href="{{ route('admin.users.create') }}" class="font-medium text-brand-600 hover:underline">Add the first one</a>.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$users" noun="user" />
    </div>
</x-admin.layout>
