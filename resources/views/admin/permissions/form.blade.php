@php($editing = $permission->exists)

<x-admin.layout :title="$editing ? 'Edit '.$permission->name : 'Add permission'" :breadcrumb="['Permissions', $editing ? $permission->name : 'Add permission']">
    <a href="{{ route('admin.permissions.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Permissions
    </a>

    <form
        method="POST"
        action="{{ $editing ? route('admin.permissions.update', $permission) : route('admin.permissions.store') }}"
        x-data="permissionForm(@js([
            'module' => old('module', $permission->module),
            'name' => old('name', $permission->name),
            'nameTouched' => $editing || old('name'),
        ]))"
        @input="dirty = true"
        class="mt-3 space-y-3"
    >
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-admin.form-heading icon="key">
            <h1 class="truncate font-mono text-xl font-bold tracking-tight" x-text="name || 'New permission'">{{ $permission->name ?: 'New permission' }}</h1>
        </x-admin.form-heading>

        <div class="card space-y-4 p-4 sm:p-5">
            <div>
                <x-admin.input
                    name="module"
                    label="Module"
                    icon="tag"
                    :value="old('module', $permission->module)"
                    x-model="module"
                    x-on:input="suggestName()"
                    list="module-names"
                    required
                    maxlength="60"
                    placeholder="e.g. Customers"
                />
                <datalist id="module-names">
                    @foreach ($modules as $module)
                        <option value="{{ $module }}">
                    @endforeach
                </datalist>

                @if ($modules->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($modules as $module)
                            <button
                                type="button"
                                @click="pickModule(@js($module))"
                                class="rounded-full border px-2.5 py-0.5 text-xs font-medium transition"
                                :class="module === @js($module) ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                            >{{ $module }}</button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <x-admin.input
                    name="name"
                    label="Permission name"
                    icon="code"
                    :value="old('name', $permission->name)"
                    x-model="name"
                    x-ref="name"
                    x-on:input="nameTouched = true"
                    required
                    maxlength="100"
                    pattern="[a-z][a-z0-9\-]*\.[a-z][a-z0-9\-]*"
                    placeholder="e.g. customers.export"
                    autocomplete="off"
                    spellcheck="false"
                    class="font-mono"
                    :hint="$editing ? 'Code that checks the old name will need updating.' : null"
                />

                <div class="mt-2 rounded-lg bg-ink-950 px-3 py-2 font-mono text-xs leading-relaxed text-slate-300">
                    <p><span class="text-brand-400">@@can</span>('<span class="text-white" x-text="name || 'module.action'"></span>') … <span class="text-brand-400">@@endcan</span></p>
                    <p>$user-><span class="text-brand-400">can</span>('<span class="text-white" x-text="name || 'module.action'"></span>')</p>
                </div>
            </div>

            <x-admin.input
                name="description"
                label="Purpose"
                icon="align-left"
                :value="old('description', $permission->description)"
                optional
                maxlength="255"
                placeholder="e.g. Export customers"
            />
        </div>

        <x-admin.form-actions>
            <a href="{{ route('admin.permissions.index') }}" class="btn-outline">Cancel</a>
            <button class="btn-gold">
                <x-icon name="check" class="size-4" />
                {{ $editing ? 'Save changes' : 'Add permission' }}
            </button>
        </x-admin.form-actions>
    </form>
</x-admin.layout>
