<x-admin.layout title="Users" :breadcrumb="['Admin', 'User & Security']">
    <x-admin.page-header title="User & Security" subtitle="Player accounts, club and venue roles, and platform access.">
        <button type="button" class="btn-gold">
            <x-icon name="user-plus" />
            Invite user
        </button>
    </x-admin.page-header>

    <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Player accounts" value="4,218" note="+312 this month" />
        <x-admin.stat label="Club admins and hosts" value="186" note="Across 150 clubs" />
        <x-admin.stat label="Coaches" value="42" note="3 WWCC checks expiring" />
        <x-admin.stat label="Listing claims" value="23" note="Waiting for review" />
    </div>

    <form method="GET" class="mt-8 flex flex-col gap-3 md:flex-row">
        <x-admin.search :value="$filters['search'] ?? ''" placeholder="Search users" class="md:flex-1" />
        <x-admin.select name="role" :options="$roles" :selected="$filters['role'] ?? null" placeholder="All roles" class="md:w-56" />
        <x-admin.select name="status" :options="$statuses" :selected="$filters['status'] ?? null" placeholder="All statuses" class="md:w-56" />
    </form>

    <div class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-left text-[15px]">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-6 py-5 font-medium">Name</th>
                        <th class="px-6 py-5 font-medium">Email</th>
                        <th class="px-6 py-5 font-medium">Role</th>
                        <th class="px-6 py-5 font-medium">Status</th>
                        <th class="px-6 py-5 font-medium">Last active</th>
                        <th class="px-6 py-5 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 border-t border-slate-200">
                    @forelse ($users as $user)
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-4">
                                    <x-admin.avatar :initials="$user['initials']" :tone="$user['tone']" />
                                    <span class="font-medium">{{ $user['name'] }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-slate-500">{{ $user['email'] }}</td>
                            <td class="px-6 py-5">
                                <p>{{ $user['role'] }}</p>
                                <p class="text-sm text-slate-500">{{ $user['detail'] }}</p>
                            </td>
                            <td class="px-6 py-5">
                                <span class="{{ match ($user['status']) { 'Active' => 'badge-gold', 'Suspended' => 'badge-danger', default => 'badge-muted' } }}">{{ $user['status'] }}</span>
                            </td>
                            <td class="px-6 py-5 text-slate-500">{{ $user['last_active'] }}</td>
                            <td class="px-6 py-5 text-right">
                                <button type="button" class="rounded-lg p-1.5 text-ink-950 hover:bg-slate-100" aria-label="Actions for {{ $user['name'] }}">
                                    <x-icon name="ellipsis" class="size-6" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">No users match your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin.layout>
