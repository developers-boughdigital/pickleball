@php($editing = $user->exists)

<x-admin.layout :title="$editing ? 'Edit '.$user->name : 'Add user'" :breadcrumb="['Users', $editing ? $user->name : 'Add user']">
    <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-ink-950">
        <x-icon name="chevron-left" class="size-4" /> Back to users
    </a>

    <div class="mt-3">
        <x-admin.page-header
            :title="$editing ? 'Edit user' : 'Add user'"
            :subtitle="$editing ? 'Change their details, password or roles.' : 'They can sign in straight away with the password you set.'"
        />
    </div>

    <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" class="mt-4 max-w-3xl space-y-4">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <section class="card space-y-4 p-4 sm:p-5">
            <h2 class="text-base font-semibold">Details</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="block font-medium">Name</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="off" @class(['field mt-1.5 h-10 w-full', 'border-red-500' => $errors->has('name')])>
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email" class="block font-medium">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="off" @class(['field mt-1.5 h-10 w-full', 'border-red-500' => $errors->has('email')])>
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password" class="block font-medium">
                        Password @if ($editing) <span class="font-normal text-slate-500">(leave blank to keep)</span> @endif
                    </label>
                    <input id="password" name="password" type="password" @required(! $editing) autocomplete="new-password" @class(['field mt-1.5 h-10 w-full', 'border-red-500' => $errors->has('password')])>
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block font-medium">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" @required(! $editing) autocomplete="new-password" class="field mt-1.5 h-10 w-full">
                </div>
            </div>
        </section>

        <section class="card p-4 sm:p-5">
            <h2 class="text-base font-semibold">Roles</h2>
            <p class="mt-0.5 text-sm text-slate-500">What this person can do comes from their roles.</p>
            @error('roles.*') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                @forelse ($roles as $role)
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 px-3 py-2.5 has-checked:border-brand-500 has-checked:bg-brand-50">
                        <input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, $assigned)) class="mt-0.5 size-4 shrink-0 accent-brand-500">
                        <span>
                            <span class="block font-medium">{{ $role->name }}</span>
                            <span class="block text-sm text-slate-500">{{ $role->description ?: $role->permissions_count.' '.str('permission')->plural($role->permissions_count) }}</span>
                        </span>
                    </label>
                @empty
                    <p class="text-slate-500">No roles yet. <a href="{{ route('admin.roles.create') }}" class="font-medium text-brand-600 hover:underline">Create one</a>.</p>
                @endforelse
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-3">
            <a href="{{ route('admin.users.index') }}" class="btn-outline h-10 px-5">Cancel</a>
            <button class="btn-gold h-10 px-6">{{ $editing ? 'Save user' : 'Add user' }}</button>
        </div>
    </form>
</x-admin.layout>
