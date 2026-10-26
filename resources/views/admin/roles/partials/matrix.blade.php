{{--
    Module × action grid. Read-only on the roles list, checkboxes on the role form.
    Expects: $modules (module => actions that exist), $actions (columns), $granted (permission names).
--}}
@php($disabled = $disabled ?? false)

<div class="mt-6 overflow-x-auto rounded-lg border border-slate-200">
    <table class="w-full min-w-[600px] text-[15px]">
        <thead class="bg-slate-50 text-slate-500">
            <tr>
                <th class="px-5 py-4 text-left font-medium">Module</th>
                @foreach ($actions as $action)
                    <th class="px-5 py-4 text-center font-medium">{{ ucfirst($action) }}</th>
                @endforeach
                @if ($editable)
                    <th class="px-5 py-4 text-center font-medium">All</th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 border-t border-slate-200">
            @forelse ($modules as $module => $available)
                <tr>
                    <th scope="row" class="px-5 py-4 text-left font-medium">{{ \App\Support\PermissionMatrix::label($module) }}</th>

                    @foreach ($actions as $action)
                        @php($name = \App\Support\PermissionMatrix::name($module, $action))
                        <td class="px-5 py-3 text-center">
                            @if (! in_array($action, $available))
                                <span class="text-slate-300" title="No {{ $name }} permission">—</span>
                            @elseif ($editable)
                                <label class="inline-grid size-11 cursor-pointer place-items-center rounded-lg hover:bg-slate-100">
                                    <span class="sr-only">{{ \App\Support\PermissionMatrix::label($module) }}: {{ $action }}</span>
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $name }}"
                                        @checked(in_array($name, $granted))
                                        @disabled($disabled)
                                        class="size-5 cursor-pointer rounded accent-brand-500"
                                    >
                                </label>
                            @elseif (in_array($name, $granted))
                                <span class="mx-auto grid size-5 place-items-center rounded bg-linear-to-br from-brand-400 to-brand-500 text-ink-950" title="Allowed">
                                    <x-icon name="check" class="size-3.5" stroke-width="3" />
                                    <span class="sr-only">Allowed</span>
                                </span>
                            @else
                                <span class="mx-auto block size-5 rounded border border-slate-300 bg-white" title="Not allowed"><span class="sr-only">Not allowed</span></span>
                            @endif
                        </td>
                    @endforeach

                    @if ($editable)
                        <td class="px-5 py-3 text-center">
                            <label class="inline-grid size-11 cursor-pointer place-items-center rounded-lg hover:bg-slate-100">
                                <span class="sr-only">All {{ \App\Support\PermissionMatrix::label($module) }} permissions</span>
                                <input
                                    type="checkbox"
                                    @disabled($disabled)
                                    x-init="$el.checked = [...$el.closest('tr').querySelectorAll('input[name=\'permissions[]\']')].every(c => c.checked)"
                                    @change="$el.closest('tr').querySelectorAll('input[name=\'permissions[]\']').forEach(c => c.checked = $el.checked)"
                                    class="size-5 cursor-pointer rounded accent-brand-500"
                                >
                            </label>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($actions) + 2 }}" class="px-5 py-10 text-center text-slate-500">
                        No permissions yet. <a href="{{ route('admin.permissions.index') }}" class="font-medium text-brand-600 hover:underline">Add a module</a> to start.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
