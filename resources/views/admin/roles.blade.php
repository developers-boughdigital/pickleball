<x-admin.layout title="Roles & Permissions" :breadcrumb="['Admin', 'User & Security']">
    <x-admin.page-header title="Roles & Permissions" subtitle="One account per person, with roles granted per club, venue or seller.">
        <button type="button" class="btn-gold">
            <x-icon name="plus" />
            Grant role
        </button>
    </x-admin.page-header>

    <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Roles" value="7" note="Fixed by the platform" />
        <x-admin.stat label="Club and venue roles" value="204" note="Granted per club or venue" />
        <x-admin.stat label="Pending grants" value="23" note="Waiting on listing claims" />
        <x-admin.stat label="Permissions" value="32" note="8 modules × 4 actions" />
    </div>

    <form method="GET" class="mt-8 flex flex-col gap-3 md:flex-row md:items-center">
        <input type="hidden" name="role" value="{{ $selected['slug'] }}">
        <x-admin.search :value="$filters['search'] ?? ''" placeholder="Search roles" class="md:flex-1" />
        <x-admin.select name="type" :options="$types" :selected="$filters['type'] ?? null" placeholder="All scopes" class="md:w-72" />
        <button type="button" class="btn-gold">
            <x-icon name="key" />
            Manage permissions
        </button>
    </form>

    <div class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left text-[15px]">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-6 py-5 font-medium">Role</th>
                        <th class="px-6 py-5 font-medium">Description</th>
                        <th class="px-6 py-5 font-medium">Users</th>
                        <th class="px-6 py-5 font-medium">Access scope</th>
                        <th class="px-6 py-5 font-medium">Updated</th>
                        <th class="px-6 py-5 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 border-t border-slate-200">
                    @forelse ($roles as $role)
                        @php($isSelected = $role['slug'] === $selected['slug'])
                        <tr @class(['transition', 'bg-brand-50/50' => $isSelected, 'hover:bg-slate-50/60' => ! $isSelected])>
                            <td class="px-6 py-5">
                                <a href="{{ request()->fullUrlWithQuery(['role' => $role['slug']]) }}"
                                   @class(['font-medium hover:underline', 'text-brand-600' => $isSelected])>{{ $role['name'] }}</a>
                            </td>
                            <td class="px-6 py-5 text-slate-500">{{ $role['description'] }}</td>
                            <td class="px-6 py-5 tabular-nums">{{ number_format($role['users']) }}</td>
                            <td class="px-6 py-5">{{ $role['scope'] }}</td>
                            <td class="px-6 py-5 text-slate-500">{{ $role['updated'] }}</td>
                            <td class="px-6 py-5 text-right">
                                <button type="button" class="rounded-lg p-1.5 hover:bg-slate-100" aria-label="Actions for {{ $role['name'] }}">
                                    <x-icon name="ellipsis" class="size-6" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">No roles match your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Permission matrix --}}
    <section class="card mt-8 p-6 sm:p-10">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold">Permission matrix preview</h2>
                <p class="mt-1 text-[15px] text-slate-500">Selected role: <span class="font-medium text-ink-950">{{ $selected['name'] }}</span></p>
            </div>
            <a href="#" class="text-[15px] font-medium text-brand-600 hover:underline">Edit permissions</a>
        </div>

        <div class="mt-6 overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full min-w-[560px] text-[15px]">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-5 py-4 text-left font-medium">Module</th>
                        @foreach ($abilities as $ability)
                            <th class="px-5 py-4 text-center font-medium">{{ ucfirst($ability) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 border-t border-slate-200">
                    @foreach ($selected['matrix'] as $module => $granted)
                        <tr>
                            <td class="px-5 py-4 font-medium">{{ $module }}</td>
                            @foreach ($abilities as $ability)
                                <td class="px-5 py-4">
                                    @if (in_array($ability, $granted))
                                        <span class="mx-auto grid size-5 place-items-center rounded bg-linear-to-br from-brand-400 to-brand-500 text-ink-950" title="Allowed">
                                            <x-icon name="check" class="size-3.5" stroke-width="3" />
                                        </span>
                                    @else
                                        <span class="mx-auto block size-5 rounded border border-slate-300 bg-white" title="Not allowed"></span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-admin.layout>
