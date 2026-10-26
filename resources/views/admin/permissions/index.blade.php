<x-admin.layout title="Permissions" :breadcrumb="['Roles & Permissions', 'Permissions']">
    <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Back to roles
    </a>

    <div class="mt-3">
        <x-admin.page-header title="Permissions" subtitle="What people can do in each module. Roles are given these on the role page.">
            <a href="{{ route('admin.permissions.create') }}" class="btn-gold">
                <x-icon name="plus" />
                Add permission
            </a>
        </x-admin.page-header>
    </div>

    <form method="GET" class="mt-4 flex flex-col gap-3 md:flex-row">
        <x-admin.search :value="$filters['search']" placeholder="Search permissions" class="md:flex-1" />
        <x-admin.select name="module" :options="$modules" :selected="$filters['module']" placeholder="All modules" class="md:w-64" />
    </form>

    <div class="card mt-3 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Module</th>
                        <th class="px-4 py-2.5 font-medium">Permission</th>
                        <th class="px-4 py-2.5 font-medium">Purpose</th>
                        <th class="px-4 py-2.5 font-medium">Roles</th>
                        <th class="px-4 py-2.5 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 border-t border-slate-200">
                    @forelse ($permissions as $permission)
                        {{-- Show the module name once, on the first row of each group. --}}
                        @php($newModule = $loop->first || $permissions[$loop->index - 1]->module !== $permission->module)
                        <tr @class(['transition hover:bg-slate-50/60', 'border-t-slate-300' => $newModule && ! $loop->first])>
                            <td class="px-4 py-2.5 font-medium">
                                @if ($newModule)
                                    {{ $permission->module ?? 'Other' }}
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <a href="{{ route('admin.permissions.edit', $permission) }}" class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[13px] text-ink-950 hover:bg-brand-50 hover:text-brand-700">{{ $permission->name }}</a>
                            </td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $permission->description ?: '—' }}</td>
                            <td class="px-4 py-2.5 tabular-nums">{{ $permission->roles_count }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('admin.permissions.edit', $permission) }}" class="grid size-8 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 hover:text-ink-950" title="Edit {{ $permission->name }}">
                                        <x-icon name="pencil" class="size-4" />
                                        <span class="sr-only">Edit {{ $permission->name }}</span>
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.permissions.destroy', $permission) }}"
                                        @submit="confirm(@js("Delete {$permission->name}?".($permission->roles_count ? " {$permission->roles_count} ".str('role')->plural($permission->roles_count).' will lose it.' : ''))) || $event.preventDefault()"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button class="grid size-8 place-items-center rounded-lg text-slate-600 hover:bg-red-50 hover:text-red-600" title="Delete {{ $permission->name }}">
                                            <x-icon name="trash" class="size-4" />
                                            <span class="sr-only">Delete {{ $permission->name }}</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                                @if (array_filter($filters))
                                    No permissions match your filters.
                                @else
                                    No permissions yet. <a href="{{ route('admin.permissions.create') }}" class="font-medium text-brand-600 hover:underline">Add the first one</a>.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="border-t border-slate-200 px-4 py-2.5 text-sm text-slate-500">
            Showing {{ $permissions->count() }} of {{ $total }} {{ str('permission')->plural($total) }}.
        </p>
    </div>
</x-admin.layout>
