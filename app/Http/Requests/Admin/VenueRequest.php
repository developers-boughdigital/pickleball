<?php

namespace App\Http\Requests\Admin;

use App\Models\Venue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;

class VenueRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::fields($this->route('venue'));
    }

    public function attributes(): array
    {
        return self::labels();
    }

    public function messages(): array
    {
        return self::messagesFor();
    }

    /** The form asks for dollars; the table (and venues.csv) keep whole cents. */
    protected function prepareForValidation(): void
    {
        if ($this->has('court_hire')) {
            $dollars = $this->input('court_hire');
            $this->merge(['court_hire_cents' => is_numeric($dollars) ? (int) round($dollars * 100) : ($dollars ?: null)]);
        }
    }

    /**
     * Shared with the import, so a row passes only if the form would accept it.
     *
     * @return array<string, array<mixed>>
     */
    public static function fields(?Venue $venue = null): array
    {
        $url = ['nullable', 'url:http,https', 'max:255'];
        $text = fn (int $max = 255) => ['nullable', 'string', "max:{$max}"];
        $list = fn (array $options) => ['nullable', Rule::in(array_keys($options))];

        return [
            'ref' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('venues')->ignore($venue)],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('venues')->ignore($venue)],
            'name' => ['required', 'string', 'max:255'],
            'name_needs_confirming' => ['nullable', 'boolean'],
            'venue_type' => $list(Venue::TYPES),

            'street_address' => $text(),
            'suburb' => $text(100),
            'postcode' => ['nullable', 'regex:/^\d{4}$/'],
            'council' => $text(100),
            'city' => $text(100),
            'state' => ['nullable', Rule::in(Venue::STATES)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'google_place_id' => $text(),

            'court_count' => ['nullable', 'integer', 'between:0,500'],
            'indoor_outdoor' => $list(Venue::SETTINGS),
            'surface' => $list(Venue::SURFACES),
            'court_lines' => $list(Venue::COURT_LINES),
            ...array_fill_keys(array_keys(Venue::FACILITIES), ['nullable', 'boolean']),

            'court_hire_cents' => ['nullable', 'integer', 'between:0,1000000'],
            'price_notes' => $text(1000),
            'booking_url' => $url,
            'opening_hours_text' => $text(1000),
            'website' => $url,
            'phone' => $text(30),

            'source_type' => $text(),
            // Usually a link, but the pack also names files here ("Client file: ....xlsx").
            'source_url' => $text(500),
            'record_status' => ['required', Rule::in(array_keys(Venue::RECORD_STATUSES))],
            'checked_at' => ['nullable', 'date'],
            'checked_by' => $text(100),
            'profile_tier' => ['required', 'integer', 'between:1,9'],
            'claim_status' => ['required', Rule::in(array_keys(Venue::CLAIM_STATUSES))],
            'enriched_at' => ['nullable', 'date'],
            'notes' => $text(5000),
            'status' => [
                'required',
                Rule::in(array_keys(Venue::STATUSES)),
                Rule::when(fn (Fluent $input) => $input->get('record_status') !== 'checked', [Rule::notIn(['published'])]),
            ],
        ];
    }

    /**
     * Error messages name the field the way the form does ("the Number of courts field ...").
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            ...Venue::FACILITIES,
            'ref' => 'Ref',
            'slug' => 'Slug',
            'name' => 'Venue name',
            'suburb' => 'Suburb',
            'postcode' => 'Postcode',
            'state' => 'State',
            'latitude' => 'Latitude',
            'longitude' => 'Longitude',
            'surface' => 'Surface',
            'website' => 'Website',
            'phone' => 'Phone',
            'notes' => 'Notes',
            'status' => 'Status',
            'name_needs_confirming' => 'Name needs confirming',
            'venue_type' => 'Venue type',
            'street_address' => 'Street address',
            'council' => 'Council (LGA)',
            'city' => 'City or region',
            'court_count' => 'Number of courts',
            'indoor_outdoor' => 'Indoor/outdoor',
            'court_lines' => 'Court lines',
            'court_hire_cents' => 'Court hire',
            'price_notes' => 'Price notes',
            'booking_url' => 'Booking link',
            'opening_hours_text' => 'Opening hours',
            'google_place_id' => 'Google place ID',
            'source_type' => 'Source type',
            'source_url' => 'Source URL',
            'record_status' => 'Record status',
            'checked_at' => 'Date checked',
            'checked_by' => 'Checked by',
            'profile_tier' => 'Profile tier',
            'claim_status' => 'Claim status',
            'enriched_at' => 'Date enriched',
        ];
    }

    /** @return array<string, string> */
    public static function messagesFor(): array
    {
        return ['status.not_in' => 'Only venues with record status “Checked” can be published.'];
    }
}
