{{--
    Permission checkboxes for the role form, grouped by module.
    Expects: $modules (module name => permissions), $granted (permission names),
    and optionally $disabled (true for the super admin, who always has everything).
--}}
@php($disabled = $disabled ?? false)

<div class="mt-3 space-y-3">
    @forelse ($modules as $module => $permissions)
        <fieldset x-data class="rounded-lg border border-slate-200">
            <legend class="sr-only">{{ $module }}</legend>

            <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2">
                <span class="font-medium">{{ $module }}</span>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                    <input
                        type="checkbox"
                        @disabled($disabled)
                        x-init="$el.checked = [...$root.querySelectorAll('input[name=\'permissions[]\']')].every(c => c.checked)"
                        @change="$root.querySelectorAll('input[name=\'permissions[]\']').forEach(c => c.checked = $el.checked)"
                        class="size-4 cursor-pointer rounded accent-brand-500"
                    >
                    Select all
                </label>
            </div>

            <div class="grid gap-1 p-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($permissions as $permission)
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-md px-2 py-1.5 hover:bg-slate-50">
                        <input
                            type="checkbox"
                            name="permissions[]"
                            value="{{ $permission->name }}"
                            @checked(in_array($permission->name, $granted))
                            @disabled($disabled)
                            @change="$root.querySelector('input:not([name])').checked = [...$root.querySelectorAll('input[name=\'permissions[]\']')].every(c => c.checked)"
                            class="mt-0.5 size-4 shrink-0 cursor-pointer rounded accent-brand-500"
                        >
                        <span class="min-w-0">
                            <span class="block font-mono text-[13px]">{{ $permission->name }}</span>
                            @if ($permission->description)
                                <span class="block text-sm text-slate-500">{{ $permission->description }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @empty
        <p class="rounded-lg border border-dashed border-slate-300 px-3 py-6 text-center text-slate-500">
            No permissions yet. <a href="{{ route('admin.permissions.create') }}" class="font-medium text-brand-600 hover:underline">Add one</a> to start.
        </p>
    @endforelse
</div>
