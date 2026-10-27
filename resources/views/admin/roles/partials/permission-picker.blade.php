{{-- Permission checkboxes for the role form, one card per module. --}}
@php($locked ??= false)

<div x-data="permissionPicker" @change="refresh()">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600">
            <span class="font-semibold text-ink-950" x-text="selected">{{ count($granted) }}</span>
            of <span x-text="total">{{ $modules->flatten()->count() }}</span> selected
        </p>

        @if ($locked)
            <p class="flex items-center gap-1.5 text-sm text-brand-700">
                <x-icon name="lock" class="size-4" /> Always has every permission
            </p>
        @elseif ($modules->isNotEmpty())
            <div class="flex gap-1.5">
                <button type="button" @click="setAll(true)" class="btn-outline h-7 px-2.5 text-xs">Select all</button>
                <button type="button" @click="setAll(false)" class="btn-outline h-7 px-2.5 text-xs">Clear</button>
            </div>
        @endif
    </div>

    <div class="grid gap-3 lg:grid-cols-2 2xl:grid-cols-3">
        @forelse ($modules as $module => $permissions)
            <fieldset
                x-data="{ count: 0, total: {{ $permissions->count() }} }"
                x-init="count = countChecked($el)"
                @change="count = countChecked($el)"
                @permissions-changed.window="count = countChecked($el)"
                class="overflow-hidden rounded-xl border transition"
                :class="count === total ? 'border-brand-300' : 'border-slate-200'"
            >
                <legend class="sr-only">{{ $module }}</legend>

                <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50/70 px-3 py-2">
                    <span class="text-sm font-semibold">{{ $module }}</span>
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[11px] font-medium tabular-nums"
                        :class="count === total ? 'bg-brand-500 text-ink-950' : (count ? 'bg-brand-100 text-brand-700' : 'bg-slate-200 text-slate-600')"
                        x-text="`${count} / ${total}`"
                    ></span>

                    <label class="ml-auto flex cursor-pointer items-center gap-2 text-xs text-slate-500">
                        Select all
                        <input
                            type="checkbox"
                            class="peer sr-only"
                            :checked="count === total"
                            @change.stop="setAll($event.target.checked, $el.closest('fieldset'))"
                            @disabled($locked)
                        >
                        <span class="relative h-4 w-7 rounded-full bg-slate-300 transition peer-checked:bg-brand-500 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300 peer-disabled:opacity-50 after:absolute after:top-0.5 after:left-0.5 after:size-3 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-3"></span>
                    </label>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach ($permissions as $permission)
                        <label @class(['flex items-start gap-2.5 px-3 py-2 transition has-checked:bg-brand-50/60', 'cursor-pointer hover:bg-slate-50' => ! $locked])>
                            <input
                                type="checkbox"
                                name="permissions[]"
                                value="{{ $permission->name }}"
                                @checked(in_array($permission->name, $granted))
                                @disabled($locked)
                                class="mt-0.5 size-4 shrink-0 cursor-pointer rounded accent-brand-500"
                            >
                            <span class="min-w-0">
                                <span class="block text-sm">{{ $permission->description ?: $permission->name }}</span>
                                <code class="block text-[11px] text-slate-400">{{ $permission->name }}</code>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @empty
            <p class="rounded-lg border border-dashed border-slate-300 px-3 py-6 text-center text-sm text-slate-500 lg:col-span-2 2xl:col-span-3">
                No permissions yet. <a href="{{ route('admin.permissions.create') }}" class="font-medium text-brand-600 hover:underline">Add one</a>.
            </p>
        @endforelse
    </div>
</div>
