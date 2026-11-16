{{-- "Claim this listing": the claimant's role and how we can check it. --}}
<x-layout :title="'Claim '.$listing->name">
    <div class="mx-auto max-w-[640px]">
        <p><a href="{{ $listingUrl }}" class="site-link font-normal">← Back to {{ $listing->name }}</a></p>

        <div class="mt-4 rounded-[20px] bg-white p-6 shadow-[0_12px_32px_rgb(15_27_45/0.14)] sm:p-8">
            <p class="font-bold text-gum-text">Claim this listing</p>
            <h1 class="mt-1 font-display text-4xl leading-[42px] font-bold">{{ $listing->name }}</h1>
            <p class="mt-1 text-muted">{{ collect([$listing->suburb, $listing->state])->filter()->implode(', ') }}</p>

            @if ($manages)
                <p class="mt-6 rounded-xl bg-gum-soft px-4 py-3 text-gum-text">You already manage this listing.</p>
                <a href="{{ route('manage.edit', [$kind, $listing]) }}" class="site-btn-primary mt-4">Edit this listing</a>
            @elseif ($pending)
                <p class="mt-6 rounded-xl bg-ball-soft px-4 py-3">Your claim from {{ $pending->created_at->format('j F') }} is with our team. We’ll email you at <strong>{{ auth()->user()->email }}</strong> once it’s checked.</p>
                <a href="{{ route('account') }}" class="site-btn-outline mt-4">See your claims</a>
            @elseif (! $listing->canBeClaimed())
                <p class="mt-6 rounded-xl bg-court-soft px-4 py-3">
                    {{ $listing->listing_status === 'claimed' ? 'This listing already has a manager. Ask them to add you to the '.$kind.'’s team.' : 'This listing can’t be claimed at the moment. Get in touch with us if it’s yours.' }}
                </p>
            @else
                <p class="mt-4 text-lg leading-7 text-muted">
                    Once we’ve checked it’s you, you can keep the {{ $kind === 'club' ? 'sessions, fees, levels and contacts' : 'courts, facilities, prices and hours' }} up to date yourself.
                    Someone on our team checks every claim by hand, usually within two working days.
                </p>

                @error('claim') <p class="mt-4 rounded-xl bg-[#fde8e6] px-4 py-3 font-bold text-[#b42318]">{{ $message }}</p> @enderror

                <form method="POST" action="{{ route('claims.store', [$kind, $listing]) }}" class="mt-6 flex flex-col gap-5">
                    @csrf

                    <div class="flex flex-col gap-1.5">
                        <label for="role_stated" class="font-bold">Your role {{ $kind === 'club' ? 'at the club' : 'at the venue' }}</label>
                        <input id="role_stated" name="role_stated" value="{{ old('role_stated') }}" list="claim-roles" required maxlength="100" class="site-field" placeholder="{{ $roles[0] }}">
                        <datalist id="claim-roles">
                            @foreach ($roles as $role) <option value="{{ $role }}"></option> @endforeach
                        </datalist>
                        @error('role_stated') <p class="font-bold text-[#b42318]">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="evidence" class="font-bold">How can we check?</label>
                        <p class="text-muted" id="evidence-help">
                            The quickest way: an email address at the {{ $kind }}’s own domain, or a link to a committee or staff page that lists you.
                            You’re signed in as <strong>{{ auth()->user()->email }}</strong>.
                        </p>
                        <textarea id="evidence" name="evidence" rows="4" required maxlength="2000" aria-describedby="evidence-help" class="site-field min-h-32 py-3"
                            placeholder="For example: I’m the secretary, listed at {{ $listing->website ?: 'our-club.org.au/committee' }}. You can reach me at secretary@…">{{ old('evidence') }}</textarea>
                        @error('evidence') <p class="font-bold text-[#b42318]">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" name="confirm" value="1" @checked(old('confirm')) required class="mt-0.5 size-6 shrink-0 accent-court">
                        <span>I’m allowed to manage this listing for the {{ $kind }}, and what I’ve written is true.</span>
                    </label>
                    @error('confirm') <p class="font-bold text-[#b42318]">{{ $message }}</p> @enderror

                    <button class="site-btn-primary min-h-14 text-lg">Send my claim</button>
                </form>
            @endif
        </div>
    </div>
</x-layout>
