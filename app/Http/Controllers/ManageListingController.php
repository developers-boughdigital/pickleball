<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsListing;
use App\Http\Requests\Admin\ClubRequest;
use App\Http\Requests\Admin\VenueRequest;
use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * The club admin's or venue manager's editor for their own listing, after an
 * approved claim. Just the public details; the name, address, location and
 * publishing stay with the platform team. The full club area is plan step 15.
 */
class ManageListingController extends Controller
{
    use FindsListing;

    /** What an owner can change, per kind. Rules come from the admin form's, so both agree. */
    public const FIELDS = [
        'club' => [
            'short_description', 'description', 'levels', 'session_times_text',
            'beginners_welcome', 'come_and_try', 'equipment_provided', 'juniors', 'seniors', 'womens_sessions', 'coaching_available',
            'casual_fee_cents', 'membership_fee_cents', 'membership_notes', 'booking_url',
            'email', 'phone', 'website', 'facebook', 'instagram',
        ],
        'venue' => [
            'court_count', 'indoor_outdoor', 'surface', 'court_lines',
            // ...plus every Venue::FACILITIES yes/no, added in fields().
            'court_hire_cents', 'price_notes', 'opening_hours_text', 'booking_url', 'website', 'phone',
        ],
    ];

    /** Typed in dollars on the form. */
    private const MONEY = ['casual_fee' => 'casual_fee_cents', 'membership_fee' => 'membership_fee_cents', 'court_hire' => 'court_hire_cents'];

    public function edit(Request $request, string $kind, string $slug): View
    {
        $listing = $this->editable($request, $kind, $slug);

        return view('manage.edit', [
            'kind' => $kind,
            'listing' => $listing,
            'listingUrl' => $this->publicUrl($listing),
            'fields' => $this->fields($kind),
        ]);
    }

    public function update(Request $request, string $kind, string $slug): RedirectResponse
    {
        $listing = $this->editable($request, $kind, $slug);

        foreach (self::MONEY as $field => $column) {
            if ($request->has($field)) {
                $dollars = $request->input($field);
                $request->merge([$column => is_numeric($dollars) ? (int) round($dollars * 100) : ($dollars ?: null)]);
            }
        }
        if ($kind === 'club') {
            $request->merge(['levels' => $request->input('levels') ?: null]);
        }

        $request->validate(
            $this->rules($kind, $listing),
            [],
            $kind === 'club' ? ClubRequest::labels() : VenueRequest::labels(),
        );
        $data = $request->only($this->fields($kind));

        $listing->fill($data);
        $changes = collect($listing->getDirty())
            ->mapWithKeys(fn ($value, string $field) => [$field => ['from' => $listing->getOriginal($field), 'to' => $listing->getAttribute($field)]])
            ->all();

        if (! $changes) {
            return back()->with('status', 'Nothing to change.');
        }

        $listing->save();
        ActivityLog::record("{$kind}.owner_updated", "Updated {$listing->name} as its ".($kind === 'club' ? 'club admin' : 'venue manager'), $listing, $listing->name, $changes);

        return back()->with('status', 'Saved. Your changes are live.');
    }

    private function editable(Request $request, string $kind, string $slug): Club|Venue
    {
        $listing = $this->findListing($kind, $slug);
        abort_unless($listing->isEditableBy($request->user()), 403, 'You don’t manage this listing.');

        return $listing;
    }

    /** The owner fields that exist on this kind of listing, so a missing column can't sneak in. */
    private function fields(string $kind): array
    {
        $wanted = $kind === 'venue' ? [...self::FIELDS['venue'], ...array_keys(Venue::FACILITIES)] : self::FIELDS['club'];
        $columns = array_keys($kind === 'club' ? ClubRequest::fields() : VenueRequest::fields());

        return array_values(array_intersect($wanted, $columns));
    }

    private function rules(string $kind, Club|Venue $listing): array
    {
        $all = $kind === 'club' ? ClubRequest::fields($listing) : VenueRequest::fields($listing);
        $fields = $this->fields($kind);

        return Arr::only($all, [...$fields, ...array_map(fn (string $field) => "{$field}.*", $fields)]);
    }
}
