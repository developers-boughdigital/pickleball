<?php

namespace App\Support;

use App\Http\Requests\Admin\VenueRequest;
use App\Models\Venue;

/** venues.csv from the directory import pack. */
class VenueSpreadsheet extends DirectorySpreadsheet
{
    protected const MODEL = Venue::class;

    protected const REQUEST = VenueRequest::class;

    public const NAME = 'Venues';

    public const COLUMNS = [
        'ref', 'slug', 'name', 'name_needs_confirming', 'venue_type',
        'street_address', 'suburb', 'postcode', 'council', 'city', 'state', 'latitude', 'longitude', 'google_place_id',
        'court_count', 'indoor_outdoor', 'surface', 'court_lines', 'nets', 'lights', 'covered', 'nets_provided', 'free_to_use',
        'court_hire_cents', 'price_notes', 'booking_required', 'booking_url', 'opening_hours_text',
        'accessible', 'toilets', 'parking', 'changing_rooms', 'cafe_kiosk', 'paddle_hire', 'website', 'phone',
        'source_type', 'source_url', 'record_status', 'checked_at', 'checked_by', 'profile_tier', 'claim_status', 'enriched_at', 'notes',
        'status',
    ];

    protected const BOOLEANS = [
        'name_needs_confirming', 'nets', 'lights', 'covered', 'nets_provided', 'free_to_use', 'booking_required',
        'accessible', 'toilets', 'parking', 'changing_rooms', 'cafe_kiosk', 'paddle_hire',
    ];

    protected const NUMERIC = ['latitude', 'longitude', 'court_count', 'court_hire_cents', 'profile_tier'];

    protected const URLS = ['website', 'booking_url'];

    protected const CODED = ['venue_type', 'indoor_outdoor', 'surface', 'court_lines', 'record_status', 'claim_status', 'status'];

    protected function clean(string $field, mixed $value): mixed
    {
        // Not nullable: an empty cell means the name is fine.
        return $field === 'name_needs_confirming' ? (parent::clean($field, $value) ?? false) : parent::clean($field, $value);
    }
}
