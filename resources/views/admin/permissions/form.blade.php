@php($editing = $permission->exists)

<x-admin.layout :title="$editing ? 'Edit '.$permission->name : 'Add permission'" :breadcrumb="['Permissions', $editing ? $permission->name : 'Add permission']">
    <a href="{{ route('admin.permissions.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Back to permissions
    </a>

    <div class="mt-3">
        <x-admin.page-header
            :title="$editing ? 'Edit permission' : 'Add permission'"
            subtitle="A permission is one thing people can do, named module.action (for example users.view)."
        />
    </div>

    <form
        method="POST"
        action="{{ $editing ? route('admin.permissions.update', $permission) : route('admin.permissions.store') }}"
        x-data="{
            module: @js(old('module', $permission->module ?? '')),
            name: @js(old('name', $permission->name ?? '')),
            nameEdited: {{ $editing || old('name') ? 'true' : 'false' }},
            slug(value) {
                return value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            },
            suggestName() {
                // Until the name is typed by hand, start it with the module: Customers → customers.
                if (! this.nameEdited) this.name = this.module ? this.slug(this.module) + '.' : '';
            },
        }"
        class="card mt-4 max-w-2xl space-y-4 p-4 sm:p-5"
    >
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div>
            <label for="module" class="block text-sm font-medium">Module</label>
            <input
                id="module"
                name="module"
                x-model="module"
                @input="suggestName()"
                list="module-names"
                required
                maxlength="60"
                placeholder="e.g. Customers"
                @class(['field mt-1 w-full', 'border-red-500' => $errors->has('module')])
            >
            <datalist id="module-names">
                @foreach ($modules as $name)
                    <option value="{{ $name }}">
                @endforeach
            </datalist>
            <p class="mt-1 text-sm text-slate-500">Pick an existing module or type a new one.</p>
            @error('module') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="name" class="block text-sm font-medium">Permission</label>
            <input
                id="name"
                name="name"
                x-model="name"
                @input="nameEdited = true"
                required
                maxlength="100"
                pattern="[a-z][a-z0-9\-]*\.[a-z][a-z0-9\-]*"
                placeholder="e.g. customers.export"
                autocomplete="off"
                spellcheck="false"
                @class(['field mt-1 w-full font-mono', 'border-red-500' => $errors->has('name')])
            >
            <p class="mt-1 text-sm text-slate-500">
                Lowercase <code>module.action</code>. Code checks this name, e.g. <code>@@can('customers.export')</code>
                @if ($editing)
                    — renaming it means updating that code too.
                @endif
            </p>
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-medium">Purpose <span class="font-normal text-slate-500">(optional)</span></label>
            <input
                id="description"
                name="description"
                value="{{ old('description', $permission->description) }}"
                maxlength="255"
                placeholder="e.g. Export customers"
                @class(['field mt-1 w-full', 'border-red-500' => $errors->has('description')])
            >
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-4">
            <a href="{{ route('admin.permissions.index') }}" class="btn-outline">Cancel</a>
            <button class="btn-gold">{{ $editing ? 'Save permission' : 'Add permission' }}</button>
        </div>
    </form>
</x-admin.layout>
