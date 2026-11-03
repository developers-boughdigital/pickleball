<?php

namespace App\Http\Requests\Admin;

use App\Models\Club;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClubRequest extends DirectoryRequest
{
    protected const MONEY = [
        'membership_fee' => 'membership_fee_cents',
        'casual_fee' => 'casual_fee_cents',
        'court_hire' => 'court_hire_cents',
    ];

    /** The form also sets which venues the club plays at. */
    public function rules(): array
    {
        $venue = Rule::exists('venues', 'ref')->withoutTrashed();

        return [
            ...parent::rules(),
            'main_venue' => ['nullable', 'string', $venue],
            'other_venues' => ['nullable', 'array'],
            'other_venues.*' => ['string', 'distinct', $venue],
        ];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'main_venue' => 'Main venue', 'other_venues.*' => 'Other venue'];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'main_venue.exists' => 'There is no venue :input.', 'other_venues.*.exists' => 'There is no venue :input.'];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Typed as comma-separated text: "social, competitive" and "V0001, V0002".
        $list = fn (string $field) => Str::of((string) $this->input($field))->explode(',')->map(fn ($item) => trim($item))->filter()->values();

        $this->merge([
            // No level ticked clears them, rather than leaving the old ones.
            'levels' => $this->input('levels') ?: null,
            'session_types' => $list('session_types_text')->map(fn ($type) => Str::snake(Str::lower($type)))->all() ?: null,
            'main_venue' => Str::upper(trim((string) $this->input('main_venue'))) ?: null,
            'other_venues' => $list('other_venues_text')->map(fn ($ref) => Str::upper($ref))->all(),
        ]);
    }

    /** Validated club columns, without the venue links. */
    public function clubData(): array
    {
        return collect($this->validated())->except(['main_venue', 'other_venues'])->all();
    }

    /** venue ref => is_primary, main venue first. */
    public function venueRefs(): array
    {
        $refs = array_fill_keys($this->validated('other_venues') ?? [], false);
        $main = $this->validated('main_venue');

        return $main ? [$main => true] + array_diff_key($refs, [$main => true]) : $refs;
    }

    public static function fields(?Model $listing = null): array
    {
        return [
            ...static::listingFields('clubs', $listing),
            'short_description' => static::textRule(500),
            'description' => static::textRule(10000),
            'levels' => ['nullable', 'array'],
            'levels.*' => ['distinct', Rule::in(array_keys(Club::LEVELS))],
            'session_types' => ['nullable', 'array'],
            'session_types.*' => ['distinct', 'regex:/^[a-z0-9_]{1,40}$/'],
            'session_times_text' => static::textRule(2000),
            ...array_fill_keys(array_keys(Club::FEATURES), ['nullable', 'boolean']),
            'state_affiliation' => static::textRule(100),

            ...array_fill_keys(array_keys(Club::FEES), ['nullable', 'integer', 'between:0,10000000']),
            'membership_notes' => static::textRule(2000),
            'booking_method' => static::listRule(Club::BOOKING_METHODS),
            'booking_url' => static::urlRule(),
            'calendar_url' => static::urlRule(),
            'coach_names_text' => static::textRule(2000),
            'leagues_text' => static::textRule(2000),

            'email' => ['nullable', 'email', 'max:255'],
            'phone' => static::textRule(30),
            ...array_fill_keys(array_keys(Club::SOCIALS), static::urlRule()),
            'year_established' => ['nullable', 'integer', 'between:1900,2100'],
            'member_count' => ['nullable', 'integer', 'between:0,1000000'],
            'enrichment_evidence' => static::textRule(10000),
        ];
    }

    public static function labels(): array
    {
        return [
            ...static::listingLabels(),
            ...Club::FEATURES,
            ...Club::FEES,
            ...Club::SOCIALS,
            'name' => 'Club name',
            'short_description' => 'Short description',
            'description' => 'Description',
            'levels' => 'Levels',
            'levels.*' => 'Level',
            'session_types' => 'Session types',
            'session_types.*' => 'Session type',
            'session_times_text' => 'Session times',
            'state_affiliation' => 'State affiliation',
            'membership_notes' => 'Membership notes',
            'booking_method' => 'Booking method',
            'calendar_url' => 'Calendar link',
            'coach_names_text' => 'Coaches',
            'leagues_text' => 'Leagues',
            'email' => 'Email',
            'year_established' => 'Year established',
            'member_count' => 'Members',
            'enrichment_evidence' => 'Enrichment evidence',
        ];
    }

    public static function messagesFor(): array
    {
        return ['status.not_in' => 'Only clubs with record status “Checked” can be published.'];
    }
}
