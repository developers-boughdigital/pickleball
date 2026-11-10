<?php

namespace App\Http\Controllers;

use App\Models\ListingReport;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** "Tell us about a court" and "report incorrect details" from the public site. */
class ListingReportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Bots fill every field; people never see this one.
        if (filled($request->input('website'))) {
            return back()->with('report_sent', true);
        }

        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(ListingReport::KINDS))],
            'venue' => ['nullable', 'string', 'exists:venues,slug'],
            'state' => ['nullable', Rule::in(Venue::STATES)],
            'area' => ['nullable', 'string', 'max:150'],
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'details' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'details.required' => 'Tell us where the court is, or what needs fixing.',
            'details.min' => 'Tell us a little more, so we can find it.',
        ]);

        $venue = isset($data['venue']) ? Venue::firstWhere('slug', $data['venue']) : null;

        ListingReport::create([
            'kind' => $data['kind'],
            'reportable_type' => $venue ? $venue->getMorphClass() : null,
            'reportable_id' => $venue?->getKey(),
            'state' => $data['state'] ?? $venue?->state,
            'area' => $data['area'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'details' => $data['details'],
            'ip_hash' => hash('sha256', $request->ip().config('app.key')),
        ]);

        return back()->with('report_sent', true)->withFragment('tell-us');
    }
}
