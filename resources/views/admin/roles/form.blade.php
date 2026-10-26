@php($editing = $role->exists)

<x-admin.layout :title="$editing ? 'Edit '.$role->name : 'Create role'" :breadcrumb="['Roles & Permissions', $editing ? $role->name : 'Create role']">
    <a href="{{ route('admin.roles.index', $editing ? ['role' => $role] : []) }}" class="inline-flex items-center gap-1.5 text-[15px] text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Back to roles
    </a>

    <div class="mt-4">
        <x-admin.page-header
            :title="$editing ? 'Edit role' : 'Create role'"
            :subtitle="$editing ? 'Change the name and what this role can do.' : 'Name the role, then tick what it can do.'"
        />
    </div>

    <form method="POST" action="{{ $editing ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="mt-8 space-y-8">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <section class="card space-y-6 p-6 sm:p-10">
            <h2 class="text-xl font-semibold">Details</h2>

            <div class="grid gap-6 lg:grid-cols-2">
                <div>
                    <label for="name" class="block font-medium">Name</label>
                    <input
                        id="name"
                        name="name"
                        value="{{ old('name', $role->name) }}"
                        required
                        maxlength="60"
                        @readonly($role->isProtected())
                        @class(['field mt-2 h-12 w-full text-base', 'bg-slate-50 text-slate-500' => $role->isProtected(), 'border-red-500' => $errors->has('name')])
                    >
                    @if ($role->isProtected())
                        <p class="mt-1.5 text-sm text-slate-500">This role can’t be renamed.</p>
                    @endif
                    @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="description" class="block font-medium">Description <span class="font-normal text-slate-500">(optional)</span></label>
                    <input
                        id="description"
                        name="description"
                        value="{{ old('description', $role->description) }}"
                        maxlength="255"
                        @class(['field mt-2 h-12 w-full text-base', 'border-red-500' => $errors->has('description')])
                    >
                    @error('description') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="card p-6 sm:p-10">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold">Permissions</h2>
                    <p class="mt-1 text-[15px] text-slate-500">
                        @if ($role->isProtected())
                            {{ $role->name }} always has every permission, including ones added later.
                        @else
                            Tick what this role can do in each module.
                        @endif
                    </p>
                </div>
                <a href="{{ route('admin.permissions.index') }}" class="text-[15px] font-medium text-brand-600 hover:underline">Manage permissions</a>
            </div>

            @error('permissions') <p class="mt-4 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('permissions.*') <p class="mt-4 text-sm text-red-600">{{ $message }}</p> @enderror

            @include('admin.roles.partials.matrix', ['editable' => true, 'disabled' => $role->isProtected()])
        </section>

        <div class="flex flex-wrap justify-end gap-3">
            <a href="{{ route('admin.roles.index', $editing ? ['role' => $role] : []) }}" class="btn-outline h-12 px-5">Cancel</a>
            <button class="btn-gold h-12 px-6">{{ $editing ? 'Save role' : 'Create role' }}</button>
        </div>
    </form>
</x-admin.layout>
