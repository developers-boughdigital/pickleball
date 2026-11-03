<?php

namespace App\Http\Requests\Admin;

use App\Models\Venue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;

/**
 * Validation shared by venues and clubs. fields() is also used by the import,
 * so a spreadsheet row passes only if the form would accept it.
 */
abstract class DirectoryRequest extends FormRequest
{
    /** Form field in dollars => column in whole cents. */
    protected const MONEY = [];

    /** @return array<string, array<mixed>> */
    abstract public static function fields(?Model $listing = null): array;

    /** @return array<string, string> */
    abstract public static function labels(): array;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return static::fields($this->listing());
    }

    public function attributes(): array
    {
        return static::labels();
    }

    public function messages(): array
    {
        return static::messagesFor();
    }

    /** @return array<string, string> */
    public static function messagesFor(): array
    {
        return ['status.not_in' => 'Only listings with record status “Checked” can be published.'];
    }

    /** The venue or club being edited, if any. */
    protected function listing(): ?Model
    {
        return collect($this->route()?->parameters())->first(fn ($value) => $value instanceof Model);
    }

    protected function prepareForValidation(): void
    {
        foreach (static::MONEY as $field => $column) {
            if ($this->has($field)) {
                $dollars = $this->input($field);
                $this->merge([$column => is_numeric($dollars) ? (int) round($dollars * 100) : ($dollars ?: null)]);
            }
        }
    }

    /**
     * Rules every listing has: identity, verification and publishing.
     *
     * @return array<string, array<mixed>>
     */
    protected static function listingFields(string $table, ?Model $listing): array
    {
        return [
            'ref' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique($table)->ignore($listing)],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique($table)->ignore($listing)],
            'name' => ['required', 'string', 'max:255'],
            'suburb' => static::textRule(100),
            'postcode' => ['nullable', 'regex:/^\d{4}$/'],
            'council' => static::textRule(100),
            'city' => static::textRule(100),
            'state' => ['nullable', Rule::in(Venue::STATES)],

            'source_type' => static::textRule(),
            // Usually a link, but the pack also names files here ("Client file: ....xlsx").
            'source_url' => static::textRule(500),
            'record_status' => ['required', Rule::in(array_keys(Venue::RECORD_STATUSES))],
            'checked_at' => ['nullable', 'date'],
            'checked_by' => static::textRule(100),
            'profile_tier' => ['required', 'integer', 'between:1,9'],
            'claim_status' => ['required', Rule::in(array_keys(Venue::CLAIM_STATUSES))],
            'enriched_at' => ['nullable', 'date'],
            'notes' => static::textRule(5000),
            'status' => [
                'required',
                Rule::in(array_keys(Venue::STATUSES)),
                Rule::when(fn (Fluent $input) => $input->get('record_status') !== 'checked', [Rule::notIn(['published'])]),
            ],
        ];
    }

    /** @return array<string, string> */
    protected static function listingLabels(): array
    {
        return [
            'ref' => 'Ref',
            'slug' => 'Slug',
            'name' => 'Name',
            'suburb' => 'Suburb',
            'postcode' => 'Postcode',
            'council' => 'Council (LGA)',
            'city' => 'City or region',
            'state' => 'State',
            'phone' => 'Phone',
            'website' => 'Website',
            'booking_url' => 'Booking link',
            'source_type' => 'Source type',
            'source_url' => 'Source URL',
            'record_status' => 'Record status',
            'checked_at' => 'Date checked',
            'checked_by' => 'Checked by',
            'profile_tier' => 'Profile tier',
            'claim_status' => 'Claim status',
            'enriched_at' => 'Date enriched',
            'notes' => 'Notes',
            'status' => 'Status',
        ];
    }

    protected static function textRule(int $max = 255): array
    {
        return ['nullable', 'string', "max:{$max}"];
    }

    protected static function urlRule(): array
    {
        return ['nullable', 'url:http,https', 'max:255'];
    }

    protected static function listRule(array $options): array
    {
        return ['nullable', Rule::in(array_keys($options))];
    }
}
