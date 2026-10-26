<x-admin.layout title="Permissions" :breadcrumb="['Roles & Permissions', 'Permissions']">
    <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-1.5 text-[15px] text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Back to roles
    </a>

    <div class="mt-4">
        <x-admin.page-header title="Permissions" subtitle="Each module has actions such as view, create, edit and approve. Roles are granted these on the role page." />
    </div>

    <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1fr)_24rem] xl:items-start">
        {{-- Existing permissions --}}
        <section class="card overflow-hidden">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-xl font-semibold">Modules</h2>
                <p class="mt-1 text-[15px] text-slate-500">
                    {{ $modules->count() }} {{ str('module')->plural($modules->count()) }},
                    @php($total = $modules->sum(fn ($permissions) => $permissions->count()))
                    {{ $total }} {{ str('permission')->plural($total) }}.
                    The number on each action is how many roles have it.
                </p>
            </div>

            <ul class="divide-y divide-slate-200">
                @forelse ($modules as $module => $permissions)
                    <li class="flex flex-col gap-4 px-6 py-5 sm:flex-row">
                        <div class="flex items-start justify-between gap-4 sm:w-44 sm:shrink-0 sm:flex-col sm:justify-start sm:gap-2">
                            <div>
                                <p class="font-medium">{{ \App\Support\PermissionMatrix::label($module) }}</p>
                                <code class="text-sm text-slate-500">{{ $module }}</code>
                            </div>

                            <form
                                method="POST"
                                action="{{ route('admin.permissions.destroy-module', $module) }}"
                                @submit="confirm(@js('Delete the whole '.\App\Support\PermissionMatrix::label($module).' module and its '.$permissions->count().' permissions?')) || $event.preventDefault()"
                            >
                                @csrf
                                @method('DELETE')
                                <button class="-ml-2 inline-flex h-10 items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                    <x-icon name="trash" class="size-4" />
                                    Delete module
                                </button>
                            </form>
                        </div>

                        <div class="flex flex-1 flex-wrap content-start gap-2">
                            @foreach ($permissions as $permission)
                                @php($action = str($permission->name)->after('.'))
                                <form
                                    method="POST"
                                    action="{{ route('admin.permissions.destroy', $permission) }}"
                                    @submit="confirm(@js("Delete {$permission->name}?".($permission->roles_count ? " {$permission->roles_count} ".str('role')->plural($permission->roles_count).' will lose it.' : ''))) || $event.preventDefault()"
                                    class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 pl-3 text-sm"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <span class="font-medium">{{ $action }}</span>
                                    <span class="ml-2 rounded bg-white px-1.5 text-slate-500 ring-1 ring-slate-200" title="{{ $permission->roles_count }} {{ str('role')->plural($permission->roles_count) }}">{{ $permission->roles_count }}</span>
                                    <button class="ml-1 grid size-9 place-items-center rounded-r-lg text-slate-500 hover:bg-red-50 hover:text-red-600" title="Delete {{ $permission->name }}">
                                        <x-icon name="x" class="size-4" />
                                        <span class="sr-only">Delete {{ $permission->name }}</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-12 text-center text-slate-500">No permissions yet. Add your first module with the form.</li>
                @endforelse
            </ul>
        </section>

        {{-- Add --}}
        <section class="card p-6 sm:p-8">
            <h2 class="text-xl font-semibold">Add permissions</h2>
            <p class="mt-1 text-[15px] text-slate-500">Use a new module name to add a module, or an existing one to add actions to it.</p>

            <form method="POST" action="{{ route('admin.permissions.store') }}" class="mt-6 space-y-6">
                @csrf

                <div>
                    <label for="module" class="block font-medium">Module</label>
                    <input
                        id="module"
                        name="module"
                        value="{{ old('module') }}"
                        list="module-names"
                        required
                        maxlength="40"
                        pattern="[a-z][a-z0-9\-]*"
                        placeholder="e.g. reports"
                        @class(['field mt-2 h-12 w-full text-base', 'border-red-500' => $errors->has('module')])
                    >
                    <datalist id="module-names">
                        @foreach ($modules->keys() as $name)
                            <option value="{{ $name }}">
                        @endforeach
                    </datalist>
                    <p class="mt-1.5 text-sm text-slate-500">Lowercase letters, numbers and dashes.</p>
                    @error('module') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <fieldset>
                    <legend class="font-medium">Actions</legend>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        @foreach ($standardActions as $action)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 has-checked:border-brand-500 has-checked:bg-brand-50">
                                <input type="checkbox" name="actions[]" value="{{ $action }}" @checked(in_array($action, old('actions', $errors->any() ? [] : $standardActions))) class="size-4 accent-brand-500">
                                {{ ucfirst($action) }}
                            </label>
                        @endforeach
                    </div>
                    @error('actions') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </fieldset>

                <div>
                    <label for="custom_action" class="block font-medium">Custom action <span class="font-normal text-slate-500">(optional)</span></label>
                    <input
                        id="custom_action"
                        name="custom_action"
                        value="{{ old('custom_action') }}"
                        maxlength="30"
                        pattern="[a-z][a-z0-9\-]*"
                        placeholder="e.g. export"
                        @class(['field mt-2 h-12 w-full text-base', 'border-red-500' => $errors->has('custom_action')])
                    >
                    @error('custom_action') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button class="btn-gold h-12 w-full">
                    <x-icon name="plus" />
                    Add permissions
                </button>
            </form>
        </section>
    </div>
</x-admin.layout>
