<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsListing;
use App\Models\ActivityLog;
use App\Models\ListingClaim;
use App\Notifications\ClaimReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Claim this listing" (plan step 14). The claimant is signed in, says what
 * their role is and how we can tell, and a platform admin decides in the
 * claims queue (Admin\ClaimController).
 */
class ClaimController extends Controller
{
    use FindsListing;

    public function create(Request $request, string $kind, string $slug): View|RedirectResponse
    {
        $listing = $this->findListing($kind, $slug);
        abort_unless($listing->status === 'published', 404);

        return view('claims.create', [
            'kind' => $kind,
            'listing' => $listing,
            'listingUrl' => $this->publicUrl($listing),
            'pending' => $listing->claims()->pending()->where('user_id', $request->user()->id)->first(),
            'manages' => $listing->isEditableBy($request->user()),
            'roles' => ListingClaim::SUGGESTED_ROLES[$kind],
        ]);
    }

    public function store(Request $request, string $kind, string $slug): RedirectResponse
    {
        $listing = $this->findListing($kind, $slug);
        abort_unless($listing->status === 'published', 404);
        $user = $request->user();

        if ($listing->isEditableBy($user)) {
            return redirect()->route('manage.edit', [$kind, $listing]);
        }
        if (! $listing->canBeClaimed()) {
            return back()->withErrors(['claim' => $listing->listing_status === 'claimed'
                ? 'This listing already has a manager. Ask them to add you.'
                : 'This listing can’t be claimed at the moment. Get in touch with us if it’s yours.']);
        }
        if ($listing->claims()->pending()->where('user_id', $user->id)->exists()) {
            return redirect()->route('account')->with('status', 'Your claim is already with us.');
        }

        $data = $request->validate([
            'role_stated' => ['required', 'string', 'max:100'],
            'evidence' => ['required', 'string', 'min:10', 'max:2000'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Please confirm you’re allowed to manage this listing.',
            'evidence.min' => 'Tell us a little more, so we can check.',
        ], ['role_stated' => 'your role', 'evidence' => 'how we can check']);

        $claim = $listing->claims()->create([
            'user_id' => $user->id,
            'role_stated' => $data['role_stated'],
            'evidence' => $data['evidence'],
        ]);
        $listing->refreshListingStatus();

        ActivityLog::record('claim.submitted', "Claimed {$listing->name} as {$data['role_stated']}", $listing, $listing->name);
        rescue(fn () => $user->notify(new ClaimReceived($claim)));

        return redirect()->route('account')->with('status', "Thanks. We’ve got your claim for {$listing->name} and will email you once it’s checked.");
    }

    public function destroy(Request $request, ListingClaim $claim): RedirectResponse
    {
        abort_unless($claim->user_id === $request->user()->id && $claim->status === 'pending', 403);

        $claim->withdraw();
        ActivityLog::record('claim.withdrawn', "Withdrew the claim for {$claim->claimable->name}", $claim->claimable, $claim->claimable->name);

        return redirect()->route('account')->with('status', 'Claim withdrawn.');
    }
}
