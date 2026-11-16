{{-- A player's account: the listings they run and their claims. --}}
@php
    use App\Models\ListingClaim;
    use App\Models\Club;

    $kindOf = fn ($listing) => $listing instanceof Club ? 'club' : 'venue';
    $pageOf = fn ($listing) => $listing instanceof Club ? route('explore.club', $listing) : route('explore.venue', $listing);
@endphp

<x-layout title="Your account">
    <div class="mx-auto max-w-[760px] space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-4xl leading-[42px] font-bold">Hi, {{ \Illuminate\Support\Str::before($user->name, ' ') }}</h1>
                <p class="mt-1 text-muted">{{ $user->email }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" data-reload>
                @csrf
                <button class="site-btn-outline">Log out</button>
            </form>
        </div>

        <section class="site-card p-6">
            <h2 class="site-h2">Listings you manage</h2>
            @forelse ($roles as $role)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line py-4 last:border-0 last:pb-0">
                    <div>
                        <a href="{{ $pageOf($role->listing) }}" class="site-h3 text-court hover:underline">{{ $role->listing->name }}</a>
                        <p class="text-muted">{{ $role->label() }} · {{ collect([$role->listing->suburb, $role->listing->state])->filter()->implode(', ') }}</p>
                    </div>
                    @if ($role->listing->isEditableBy($user))
                        <a href="{{ route('manage.edit', [$kindOf($role->listing), $role->listing]) }}" class="site-btn-primary">Edit listing</a>
                    @elseif ($role->listing->listing_status === 'suspended')
                        <span class="text-muted">Editing is paused. Get in touch with us.</span>
                    @endif
                </div>
            @empty
                <p class="mt-2 text-muted">
                    None yet. Run a club or manage a venue? Find it on
                    <a href="{{ route('explore.clubs') }}" class="site-link">Clubs</a> or <a href="{{ route('explore.venues') }}" class="site-link">Venues</a>
                    and press “Claim this listing”.
                </p>
            @endforelse
        </section>

        @if ($claims->isNotEmpty())
            <section class="site-card p-6">
                <h2 class="site-h2">Your claims</h2>
                @foreach ($claims as $claim)
                    <div class="border-b border-line py-4 last:border-0 last:pb-0">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <a href="{{ $pageOf($claim->claimable) }}" class="font-bold text-navy hover:text-court hover:underline">{{ $claim->claimable->name }}</a>
                                <p class="text-muted">As {{ $claim->role_stated }} · sent {{ $claim->created_at->format('j M Y') }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span @class([
                                    'site-tag',
                                    'bg-ball-soft text-navy' => $claim->status === 'pending',
                                    'bg-gum-soft text-gum-text' => $claim->status === 'approved',
                                    'bg-[#fde8e6] text-[#b42318]' => $claim->status === 'rejected',
                                    'bg-court-soft text-muted' => $claim->status === 'withdrawn',
                                ])>{{ ListingClaim::STATUSES[$claim->status] }}</span>
                                @if ($claim->status === 'pending')
                                    <form method="POST" action="{{ route('claims.destroy', $claim) }}" @submit="confirm('Withdraw this claim?') || $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <button class="site-link">Withdraw</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @if ($claim->status === 'rejected' && $claim->reject_reason)
                            <p class="mt-2 rounded-xl bg-court-soft px-4 py-2.5"><strong>Why:</strong> {{ $claim->reject_reason }}</p>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif
    </div>
</x-layout>
