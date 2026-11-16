<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ListingClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The claims queue (brief: /admin/claims): the listing, the claimant and their
 * evidence, then approve, or reject with a reason the claimant is emailed.
 */
class ClaimController extends Controller
{
    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), ListingClaim::STATUSES) ? $request->query('status') : 'pending';

        $claims = ListingClaim::query()
            ->with(['claimable', 'user', 'decider'])
            ->where('status', $status)
            // Oldest first while waiting, so nobody waits longest; newest decisions first after.
            ->orderBy($status === 'pending' ? 'created_at' : 'decided_at', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.claims.index', [
            'status' => $status,
            'claims' => $claims,
            // Someone claiming many clubs at once is worth a second look.
            'claimsPerUser' => ListingClaim::query()->whereIn('user_id', $claims->pluck('user_id'))
                ->selectRaw('user_id, count(*) as total')->groupBy('user_id')->pluck('total', 'user_id'),
            'counts' => ListingClaim::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function approve(Request $request, ListingClaim $claim): RedirectResponse
    {
        if ($claim->status !== 'pending') {
            return back()->with('status', 'That claim was already decided.');
        }

        $claim->approve($request->user());
        $listing = $claim->claimable;
        ActivityLog::record('claim.approved', "Approved {$claim->user->name} as ".($claim->kind() === 'club' ? 'club admin' : 'venue manager')." of {$listing->name}", $listing, $listing->name);

        return back()->with('status', "Approved. {$claim->user->name} now manages {$listing->name}.");
    }

    public function reject(Request $request, ListingClaim $claim): RedirectResponse
    {
        if ($claim->status !== 'pending') {
            return back()->with('status', 'That claim was already decided.');
        }

        $reason = $request->validate(['reject_reason' => ['required', 'string', 'max:1000']], [], ['reject_reason' => 'reason'])['reject_reason'];

        $claim->reject($request->user(), $reason);
        $listing = $claim->claimable;
        ActivityLog::record('claim.rejected', "Rejected {$claim->user->name}’s claim for {$listing->name}: {$reason}", $listing, $listing->name);

        return back()->with('status', "Rejected. We’ve emailed {$claim->user->name} the reason.");
    }
}
