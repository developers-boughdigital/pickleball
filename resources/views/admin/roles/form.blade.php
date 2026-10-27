@php($editing = $role->exists)

<x-admin.layout :title="$editing ? 'Edit '.$role->name : 'Create role'" :breadcrumb="['Roles & Permissions', $editing ? $role->name : 'Create role']">
    <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Roles
    </a>

    <form
        method="POST"
        action="{{ $editing ? route('admin.roles.update', $role) : route('admin.roles.store') }}"
        x-data="{ dirty: false, name: @js(old('name', $role->name)) }"
        @input="dirty = true"
        @change="dirty = true"
        class="mt-3 space-y-3"
    >
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-admin.form-heading :icon="$role->isProtected() ? 'shield-check' : 'key'">
            <h1 class="truncate text-xl font-bold tracking-tight" x-text="name || 'New role'">{{ $role->name ?: 'New role' }}</h1>
        </x-admin.form-heading>

        <div class="card grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
            <x-admin.input
                name="name"
                label="Role name"
                icon="shield-check"
                :value="old('name', $role->name)"
                x-model="name"
                required
                maxlength="60"
                placeholder="e.g. Support"
                :readonly="$role->isProtected()"
                :hint="$role->isProtected() ? 'This role can’t be renamed.' : null"
                :class="$role->isProtected() ? 'bg-slate-50 text-slate-500' : ''"
            />
            <x-admin.input
                name="description"
                label="Description"
                icon="align-left"
                :value="old('description', $role->description)"
                optional
                maxlength="255"
                placeholder="e.g. Helps people with their accounts"
            />
        </div>

        <div class="card p-4 sm:p-5">
            <p class="mb-2 text-sm font-medium">Permissions</p>
            @error('permissions.*') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror

            @include('admin.roles.partials.permission-picker', ['locked' => $role->isProtected()])
        </div>

        <x-admin.form-actions>
            <a href="{{ route('admin.roles.index') }}" class="btn-outline">Cancel</a>
            <button class="btn-gold">
                <x-icon name="check" class="size-4" />
                {{ $editing ? 'Save changes' : 'Create role' }}
            </button>
        </x-admin.form-actions>
    </form>
</x-admin.layout>
