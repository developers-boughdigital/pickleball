{{-- Who runs this listing (club admins, venue managers) and its claims, on the admin show pages. --}}
@props(['listing'])

@php
    $roles = $listing->listingRoles()->with('user')->get();
    $pending = $listing->claims()->pending()->count();
@endphp

<section {{ $attributes->class('card p-4 sm:p-5') }}>
    <div class="mb-3 flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold">Managed by</h2>
        <span class="{{ $listing->listingStatusBadge() }}">{{ $listing->label('listing_status') }}</span>
    </div>
    @forelse ($roles as $role)
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-1.5 text-sm last:border-0">
            <span class="min-w-0 truncate">{{ $role->user?->name ?? 'Deleted user' }} <span class="text-xs text-slate-500">{{ $role->user?->email }}</span></span>
            <span class="shrink-0 text-xs text-slate-500">{{ $role->label() }}</span>
        </div>
    @empty
        <p class="text-sm text-slate-500">Nobody yet. It’s managed by the platform team until someone’s claim is approved.</p>
    @endforelse
    @can('claims.view')
        @if ($pending)
            <a href="{{ route('admin.claims.index') }}" class="mt-2 inline-flex text-xs font-medium text-amber-600 hover:underline">{{ $pending }} {{ str('claim')->plural($pending) }} waiting for review</a>
        @endif
    @endcan
</section>
