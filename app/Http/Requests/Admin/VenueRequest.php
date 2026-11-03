<?php

namespace App\Http\Requests\Admin;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;

class VenueRequest extends DirectoryRequest
{
    protected const MONEY = ['court_hire' => 'court_hire_cents'];

    public static function fields(?Model $listing = null): array
    {
        return [
            ...static::listingFields('venues', $listing),
            'name_needs_confirming' => ['nullable', 'boolean'],
            'venue_type' => static::listRule(Venue::TYPES),

            'street_address' => static::textRule(),
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'google_place_id' => static::textRule(),

            'court_count' => ['nullable', 'integer', 'between:0,500'],
            'indoor_outdoor' => static::listRule(Venue::SETTINGS),
            'surface' => static::listRule(Venue::SURFACES),
            'court_lines' => static::listRule(Venue::COURT_LINES),
            ...array_fill_keys(array_keys(Venue::FACILITIES), ['nullable', 'boolean']),

            'court_hire_cents' => ['nullable', 'integer', 'between:0,1000000'],
            'price_notes' => static::textRule(1000),
            'booking_url' => static::urlRule(),
            'opening_hours_text' => static::textRule(1000),
            'website' => static::urlRule(),
            'phone' => static::textRule(30),
        ];
    }

    public static function labels(): array
    {
        return [
            ...static::listingLabels(),
            ...Venue::FACILITIES,
            'name' => 'Venue name',
            'name_needs_confirming' => 'Name needs confirming',
            'venue_type' => 'Venue type',
            'street_address' => 'Street address',
            'latitude' => 'Latitude',
            'longitude' => 'Longitude',
            'google_place_id' => 'Google place ID',
            'court_count' => 'Number of courts',
            'indoor_outdoor' => 'Indoor/outdoor',
            'surface' => 'Surface',
            'court_lines' => 'Court lines',
            'court_hire_cents' => 'Court hire',
            'price_notes' => 'Price notes',
            'opening_hours_text' => 'Opening hours',
        ];
    }

    public static function messagesFor(): array
    {
        return ['status.not_in' => 'Only venues with record status “Checked” can be published.'];
    }
}
