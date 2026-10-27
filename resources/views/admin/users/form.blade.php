@php($editing = $user->exists)

<x-admin.layout :title="$editing ? 'Edit '.$user->name : 'Add user'" :breadcrumb="['Users', $editing ? $user->name : 'Add user']">
    <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Users
    </a>

    <form
        method="POST"
        action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}"
        x-data="userForm(@js(['name' => old('name', $user->name), 'email' => old('email', $user->email)]))"
        @input="dirty = true"
        @change="dirty = true"
        class="mt-3 space-y-3"
    >
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-admin.form-heading>
            <x-slot:tile><span x-text="initials">{{ $user->initials() ?: '?' }}</span></x-slot:tile>
            <h1 class="truncate text-xl font-bold tracking-tight" x-text="name || 'New user'">{{ $user->name ?: 'New user' }}</h1>
            <p class="truncate text-sm text-slate-500" x-text="email">{{ $user->email }}</p>
        </x-admin.form-heading>

        <div class="card grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
            <x-admin.input name="name" label="Full name" icon="user" :value="old('name', $user->name)" x-model="name" required maxlength="255" autocomplete="off" placeholder="e.g. Megan Clarke" />
            <x-admin.input name="email" label="Email" type="email" icon="mail" :value="old('email', $user->email)" x-model="email" required maxlength="255" autocomplete="off" placeholder="name@example.com" />

            <div>
                <x-admin.input
                    name="password"
                    :label="$editing ? 'New password' : 'Password'"
                    type="password"
                    icon="lock"
                    x-model="password"
                    x-bind:type="showPassword ? 'text' : 'password'"
                    :required="! $editing"
                    :optional="$editing"
                    :placeholder="$editing ? 'Leave blank to keep' : null"
                    autocomplete="new-password"
                >
                    <button
                        type="button"
                        @click="showPassword = ! showPassword"
                        class="absolute top-1/2 right-1.5 grid size-6 -translate-y-1/2 place-items-center rounded text-slate-400 hover:text-ink-950"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                    >
                        <x-icon name="eye" class="size-4" x-show="! showPassword" />
                        <x-icon name="eye-off" class="size-4" x-show="showPassword" x-cloak />
                    </button>
                </x-admin.input>

                <div x-show="password" x-cloak class="mt-2 flex items-center gap-2">
                    <div class="grid flex-1 grid-cols-4 gap-1">
                        @foreach (range(1, 4) as $bar)
                            <span class="h-1 rounded-full transition" :class="strength.score >= {{ $bar }} ? strength.color : 'bg-slate-200'"></span>
                        @endforeach
                    </div>
                    <span class="w-16 text-right text-xs text-slate-500" x-text="strength.label"></span>
                </div>
            </div>

            <x-admin.input
                name="password_confirmation"
                label="Confirm password"
                type="password"
                icon="lock"
                x-bind:type="showPassword ? 'text' : 'password'"
                :required="! $editing"
                autocomplete="new-password"
            />
        </div>

        <div class="card p-4 sm:p-5">
            <p class="mb-2 text-sm font-medium">Roles</p>
            @error('roles.*') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($roles as $role)
                    <label class="group flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-slate-300 has-checked:border-brand-400 has-checked:bg-brand-50 has-checked:ring-1 has-checked:ring-brand-400 has-focus-visible:ring-2 has-focus-visible:ring-brand-300">
                        <input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, $assigned)) class="sr-only">

                        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500 transition group-has-checked:bg-brand-500 group-has-checked:text-ink-950">
                            <x-icon :name="$role->isProtected() ? 'shield-check' : 'key'" class="size-4" />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold">{{ $role->name }}</span>
                            @if ($role->description)
                                <span class="block text-xs text-slate-500">{{ $role->description }}</span>
                            @endif
                            <span class="mt-1 block text-xs text-slate-400">{{ $role->permissions_count }} {{ str('permission')->plural($role->permissions_count) }}</span>
                        </span>

                        <span class="grid size-5 shrink-0 place-items-center rounded-full border border-slate-300 bg-white text-transparent transition group-has-checked:border-brand-500 group-has-checked:bg-brand-500 group-has-checked:text-ink-950">
                            <x-icon name="check" class="size-3" stroke-width="3" />
                        </span>
                    </label>
                @empty
                    <p class="text-sm text-slate-500">No roles yet. <a href="{{ route('admin.roles.create') }}" class="font-medium text-brand-600 hover:underline">Create one</a>.</p>
                @endforelse
            </div>
        </div>

        <x-admin.form-actions>
            <a href="{{ $editing ? route('admin.users.show', $user) : route('admin.users.index') }}" class="btn-outline">Cancel</a>
            <button class="btn-gold">
                <x-icon name="check" class="size-4" />
                {{ $editing ? 'Save changes' : 'Add user' }}
            </button>
        </x-admin.form-actions>
    </form>
</x-admin.layout>
