{{-- Under the name on a club or venue page: "Claim this listing", "your claim is with us", or "you manage this". --}}
@props(['listing', 'kind'])

@php
    $user = auth()->user();
    $manages = $listing->isEditableBy($user);
    $waiting = ! $manages && $user && $listing->claims()->pending()->where('user_id', $user->id)->exists();
    $noun = $kind === 'club' ? 'club' : 'venue';
@endphp

@if ($manages)
    <div {{ $attributes->class('flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gum-soft px-4 py-3') }}>
        <p class="inline-flex items-center gap-2 font-bold text-gum-text"><x-icon name="check" class="size-5" /> You manage this {{ $noun }}. Changes go live straight away.</p>
        <a href="{{ route('manage.edit', [$kind, $listing]) }}" class="site-btn-primary">Edit this listing</a>
    </div>
@elseif ($waiting)
    <div {{ $attributes->class('flex flex-wrap items-center justify-between gap-3 rounded-xl bg-ball-soft px-4 py-3') }}>
        <p class="font-bold">Your claim for this {{ $noun }} is with our team. We’ll email you once it’s checked.</p>
        <a href="{{ route('account') }}" class="site-link">See your claims</a>
    </div>
@elseif ($listing->canBeClaimed())
    <div {{ $attributes->class('flex flex-wrap items-center justify-between gap-3 rounded-xl bg-ball-soft px-4 py-3') }}>
        <p>
            <strong>{{ $kind === 'club' ? 'Run this club?' : 'Manage this venue?' }}</strong>
            Claim this listing to keep the {{ $kind === 'club' ? 'sessions, fees and contacts' : 'courts, prices and hours' }} up to date. It’s free.
        </p>
        <a href="{{ route('claims.create', [$kind, $listing]) }}" class="site-btn-primary" rel="nofollow">Claim this listing</a>
    </div>
@elseif ($listing->listing_status === 'claimed')
    <p {{ $attributes->class('inline-flex items-center gap-2 text-muted') }}><x-icon name="check" class="size-4 text-gum-text" /> Managed by the {{ $noun }}</p>
@endif
