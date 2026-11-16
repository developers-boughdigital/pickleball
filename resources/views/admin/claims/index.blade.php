@php
    use App\Models\Club;
    use App\Models\ListingClaim;
@endphp

<x-admin.layout title="Claims" :breadcrumb="['Directory', 'Claims']">
    <x-admin.page-header title="Claims" subtitle="People asking to run a club or manage a venue. Check their evidence, then approve them, or reject with a reason they’ll be emailed." />

    <nav class="mt-4 flex flex-wrap gap-1.5" aria-label="Claim status">
        @foreach (ListingClaim::STATUSES as $key => $label)
            <a href="{{ route('admin.claims.index', ['status' => $key]) }}" @class([
                'inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-sm font-medium transition',
                'bg-ink-950 text-white' => $status === $key,
                'bg-white text-slate-600 ring-1 ring-slate-200 hover:text-ink-950' => $status !== $key,
            ])>
                {{ $key === 'pending' ? 'To review' : $label }}
                <span @class(['rounded-full px-1.5 text-xs tabular-nums', 'bg-white/20' => $status === $key, 'bg-slate-100' => $status !== $key])>{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <div class="mt-3 space-y-2">
        @forelse ($claims as $claim)
            @php
                $listing = $claim->claimable;
                $isClub = $listing instanceof Club;
                $matches = $claim->emailMatchesListing();
                $others = ($claimsPerUser[$claim->user_id] ?? 1) - 1;
            @endphp
            <article class="card p-4" x-data="{ rejecting: false }">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1 space-y-2">
                        <p class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="{{ $isClub ? 'badge-gold' : 'badge-muted' }}">{{ $isClub ? 'Club' : 'Venue' }}</span>
                            <a href="{{ $isClub ? route('admin.clubs.show', $listing) : route('admin.venues.show', $listing) }}" class="font-semibold hover:underline">{{ $listing->name }}</a>
                            <span class="text-xs text-slate-500">{{ collect([$listing->suburb, $listing->state])->filter()->implode(', ') }}</span>
                            <span class="{{ $listing->listingStatusBadge() }}">{{ $listing->label('listing_status') }}</span>
                            @if ($listing->trashed()) <span class="badge-danger">Deleted</span> @endif
                        </p>

                        <dl class="grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-[9rem_1fr]">
                            <dt class="text-slate-500">Claimant</dt>
                            <dd>
                                <span class="font-medium">{{ $claim->user->name }}</span> ·
                                <a href="mailto:{{ $claim->user->email }}" class="hover:underline">{{ $claim->user->email }}</a>
                                @if ($matches)
                                    <span class="badge-success ml-1" title="The email is on the same domain as the listing’s website or email">Email matches the {{ $isClub ? 'club' : 'venue' }}</span>
                                @endif
                                <span class="block text-xs text-slate-500">
                                    Account made {{ $claim->user->created_at->diffForHumans() }}
                                    @if ($others > 0) · <span class="font-medium text-amber-600">{{ $others }} other {{ str('claim')->plural($others) }}</span> @endif
                                </span>
                            </dd>
                            <dt class="text-slate-500">Role they gave</dt>
                            <dd>{{ $claim->role_stated }}</dd>
                            <dt class="text-slate-500">Evidence</dt>
                            <dd class="whitespace-pre-line text-ink-950">{{ $claim->evidence }}</dd>
                            <dt class="text-slate-500">On file for the listing</dt>
                            <dd class="text-slate-600">
                                {{ collect([$listing->email, $listing->website, $listing->phone])->filter()->implode(' · ') ?: 'No contact details on file' }}
                            </dd>
                            @if ($claim->status === 'rejected')
                                <dt class="text-slate-500">Reason given</dt>
                                <dd>{{ $claim->reject_reason }}</dd>
                            @endif
                        </dl>

                        <p class="text-xs text-slate-500">
                            Sent <span title="{{ $claim->created_at->format('j M Y, g:i A') }}">{{ $claim->created_at->diffForHumans() }}</span>
                            @if ($claim->decided_at) · {{ strtolower(ListingClaim::STATUSES[$claim->status]) }} by {{ $claim->decider?->name ?? 'someone' }} {{ $claim->decided_at->diffForHumans() }} @endif
                        </p>
                    </div>

                    @if ($claim->status === 'pending')
                        @can('claims.decide')
                            <div class="flex shrink-0 items-start gap-2" x-show="! rejecting">
                                <form method="POST" action="{{ route('admin.claims.approve', $claim) }}"
                                    @submit="confirm(@js("Make {$claim->user->name} the ".($isClub ? 'club admin' : 'venue manager')." of {$listing->name}?")) || $event.preventDefault()">
                                    @csrf
                                    <button class="btn-gold"><x-icon name="check" class="size-4" /> Approve</button>
                                </form>
                                <button type="button" class="btn-outline text-red-600 hover:bg-red-50" @click="rejecting = true; $nextTick(() => $refs.reason.focus())">
                                    <x-icon name="x" class="size-4" /> Reject
                                </button>
                            </div>
                        @endcan
                    @endif
                </div>

                @if ($claim->status === 'pending')
                    @can('claims.decide')
                        <form method="POST" action="{{ route('admin.claims.reject', $claim) }}" x-show="rejecting" x-cloak class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                            @csrf
                            <label for="reason-{{ $claim->id }}" class="text-sm font-medium">Why? The claimant sees this in their email.</label>
                            <textarea id="reason-{{ $claim->id }}" name="reject_reason" x-ref="reason" rows="2" required maxlength="1000" class="field h-auto w-full py-2"
                                placeholder="For example: We couldn’t match you to the club. Please claim again from your club email address."></textarea>
                            <div class="flex gap-2">
                                <button class="btn-outline text-red-600 hover:bg-red-50">Reject claim</button>
                                <button type="button" class="btn-outline" @click="rejecting = false">Cancel</button>
                            </div>
                        </form>
                    @endcan
                @endif
            </article>
        @empty
            <div class="card px-4 py-10 text-center text-sm text-slate-500">
                {{ $status === 'pending' ? 'No claims waiting. They arrive from the “Claim this listing” button on club and venue pages.' : 'None here yet.' }}
            </div>
        @endforelse
    </div>

    @error('reject_reason') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

    @if ($claims->hasPages())
        <div class="card mt-3 overflow-hidden"><x-admin.pagination :paginator="$claims" noun="claim" /></div>
    @endif
</x-admin.layout>
