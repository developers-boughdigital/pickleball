<x-admin.layout title="Roles & Permissions" :breadcrumb="['Admin', 'User & Security']">
    <x-admin.page-header title="Roles & Permissions" subtitle="Roles decide what people can view, create, edit and approve.">
        <a href="{{ route('admin.permissions.index') }}" class="btn-outline">
            <x-icon name="key" class="text-slate-600" />
            Manage permissions
        </a>
        <a href="{{ route('admin.roles.create') }}" class="btn-gold">
            <x-icon name="plus" />
            Create role
        </a>
    </x-admin.page-header>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Roles" :value="$stats['roles']" note="Defined on the platform" />
        <x-admin.stat label="People with a role" :value="number_format($stats['assignedUsers'])" note="Across all roles" />
        <x-admin.stat label="Permissions" :value="$stats['permissions']" note="Module × action" />
        <x-admin.stat label="Modules" :value="$stats['modules']" note="Areas of the platform" />
    </div>

    <form method="GET" class="mt-4">
        <x-admin.search :value="$search" placeholder="Search roles" />
    </form>

    <div class="card mt-3 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Role</th>
                        <th class="px-4 py-2.5 font-medium">Description</th>
                        <th class="px-4 py-2.5 font-medium">People</th>
                        <th class="px-4 py-2.5 font-medium">Updated</th>
                        <th class="px-4 py-2.5 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 border-t border-slate-200">
                    @forelse ($roles as $role)
                        <tr class="transition hover:bg-slate-50/60">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('admin.roles.edit', $role) }}" class="font-medium hover:underline">{{ $role->name }}</a>
                                <p class="text-sm text-slate-500">{{ $role->permissions_count }} {{ str('permission')->plural($role->permissions_count) }}</p>
                            </td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $role->description ?: '—' }}</td>
                            <td class="px-4 py-2.5 tabular-nums">{{ number_format($role->users_count) }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $role->updated_at?->diffForHumans() }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="grid size-8 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 hover:text-ink-950" title="Edit {{ $role->name }}">
                                        <x-icon name="pencil" class="size-4" />
                                        <span class="sr-only">Edit {{ $role->name }}</span>
                                    </a>
                                    @if ($role->isProtected())
                                        <span class="grid size-8 place-items-center text-slate-400" title="This role can’t be deleted">
                                            <x-icon name="lock" class="size-4" />
                                            <span class="sr-only">{{ $role->name }} can’t be deleted</span>
                                        </span>
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('admin.roles.destroy', $role) }}"
                                            @submit="confirm(@js("Delete the “{$role->name}” role?".($role->users_count ? " {$role->users_count} ".str('person')->plural($role->users_count).' will lose it.' : ''))) || $event.preventDefault()"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button class="grid size-8 place-items-center rounded-lg text-slate-600 hover:bg-red-50 hover:text-red-600" title="Delete {{ $role->name }}">
                                                <x-icon name="trash" class="size-4" />
                                                <span class="sr-only">Delete {{ $role->name }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-slate-500">
                                @if ($search)
                                    No roles match “{{ $search }}”.
                                @else
                                    No roles yet. <a href="{{ route('admin.roles.create') }}" class="font-medium text-brand-600 hover:underline">Create the first one</a>.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin.layout>
