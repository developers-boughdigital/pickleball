{{-- "Claim this listing" on a club or venue page, or "you manage this" for its admin. --}}
@props(['listing', 'kind'])

@php
    $user = auth()->user();
    $manages = $listing->isEditableBy($user);
    $noun = $kind === 'club' ? 'club' : 'venue';
@endphp

@if ($manages)
    <section class="site-card border-2 border-gum p-6">
        <h2 class="site-h3">You manage this {{ $noun }}</h2>
        <p class="mt-1 text-muted">Changes you make go live straight away.</p>
        <a href="{{ route('manage.edit', [$kind, $listing]) }}" class="site-btn-primary mt-4">Edit this listing</a>
    </section>
@elseif ($listing->canBeClaimed())
    <section class="site-card bg-ball-soft p-6">
        <h2 class="site-h3">{{ $kind === 'club' ? 'Run this club?' : 'Manage this venue?' }}</h2>
        <p class="mt-1">Claim this listing to keep the {{ $kind === 'club' ? 'sessions, fees and contacts' : 'courts, prices and hours' }} up to date. It’s free.</p>
        <a href="{{ route('claims.create', [$kind, $listing]) }}" class="site-btn-primary mt-4" rel="nofollow">Claim this listing</a>
    </section>
@elseif ($listing->listing_status === 'claimed')
    <p class="inline-flex items-center gap-2 px-1 text-muted"><x-icon name="check" class="size-4 text-gum-text" /> Managed by the {{ $noun }}</p>
@endif
