<?php

namespace App\Http\Requests\Admin;

use App\Models\Venue;
use App\Support\VenueSpreadsheet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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

    /**
     * Shared with the spreadsheet import, so a row passes only if the form would accept it.
     *
     * @return array<string, array<mixed>>
     */
    public static function fields(?Venue $venue = null): array
    {
        $url = ['nullable', 'url:http,https', 'max:255'];

        return [
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('venues')->ignore($venue)],
            'name' => ['required', 'string', 'max:255'],
            'venue_type' => ['nullable', Rule::in(Venue::TYPES)],

            'address' => ['nullable', 'string', 'max:255'],
            'suburb' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'regex:/^\d{4}$/'],
            'council' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', Rule::in(Venue::STATES)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'google_place_id' => ['nullable', 'string', 'max:255'],

            'court_count' => ['nullable', 'integer', 'between:0,500'],
            'indoor_outdoor' => ['nullable', Rule::in(Venue::SETTINGS)],
            'surface' => ['nullable', Rule::in(Venue::SURFACES)],
            'court_lines' => ['nullable', Rule::in(Venue::COURT_LINES)],
            'lights' => ['nullable', 'boolean'],
            'facilities' => ['nullable', 'array:'.implode(',', array_keys(Venue::FACILITIES))],
            'facilities.*' => ['nullable', 'boolean'],

            'price_per_hour' => ['nullable', 'numeric', 'between:0,9999'],
            'price_notes' => ['nullable', 'string', 'max:1000'],
            'booking_url' => $url,
            'opening_hours' => ['nullable', 'string', 'max:255'],
            'website' => $url,
            'phone' => ['nullable', 'string', 'max:30'],
            'club_codes' => ['nullable', 'string', 'max:255'],

            'source_type' => ['nullable', Rule::in(Venue::SOURCE_TYPES)],
            'source_url' => $url,
            'checked_at' => ['nullable', 'date'],
            'checked_by' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(Venue::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Error messages use the spreadsheet's column names ("the Number of courts field ...").
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_flip(VenueSpreadsheet::COLUMNS);
    }
}
