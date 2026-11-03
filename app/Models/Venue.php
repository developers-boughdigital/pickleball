<?php

namespace App\Models;

use App\Models\Concerns\DirectoryListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use DirectoryListing, HasUuids, SoftDeletes;

    public const REF_PREFIX = 'V';

    // Codes as they appear in venues.csv => what the admin shows.
    public const TYPES = [
        'council_outdoor' => 'Council outdoor',
        'council_indoor' => 'Council indoor',
        'leisure_centre' => 'Leisure centre',
        'commercial_indoor' => 'Commercial indoor',
        'tennis_club' => 'Tennis club',
        'school' => 'School',
        'community_hall' => 'Community hall',
        'private' => 'Private',
    ];

    public const SETTINGS = ['indoor' => 'Indoor', 'outdoor' => 'Outdoor', 'both' => 'Indoor & outdoor'];

    public const SURFACES = [
        'acrylic_hard_court' => 'Acrylic hard court',
        'asphalt' => 'Asphalt',
        'concrete' => 'Concrete',
        'synthetic_grass' => 'Synthetic grass',
        'timber' => 'Timber',
        'sprung_timber' => 'Sprung timber',
        'vinyl' => 'Vinyl',
        'other' => 'Other',
    ];

    public const COURT_LINES = [
        'dedicated_pickleball_courts' => 'Dedicated pickleball courts',
        'shared_lines' => 'Shared lines (tennis or other)',
        'both' => 'Both',
    ];

    /** Yes/No/Unknown columns, in the order venues.csv lists them. */
    public const FACILITIES = [
        'nets' => 'Nets',
        'lights' => 'Lights',
        'covered' => 'Covered',
        'nets_provided' => 'Nets provided',
        'free_to_use' => 'Free to use',
        'booking_required' => 'Booking required',
        'accessible' => 'Accessible',
        'toilets' => 'Toilets',
        'parking' => 'Parking',
        'changing_rooms' => 'Changing rooms',
        'cafe_kiosk' => 'Café or kiosk',
        'paddle_hire' => 'Paddle hire',
    ];

    /** Columns that hold one of the coded lists above. */
    public const LISTS = [
        'venue_type' => self::TYPES,
        'indoor_outdoor' => self::SETTINGS,
        'surface' => self::SURFACES,
        'court_lines' => self::COURT_LINES,
        'record_status' => self::RECORD_STATUSES,
        'claim_status' => self::CLAIM_STATUSES,
        'status' => self::STATUSES,
    ];

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $attributes = ['name_needs_confirming' => false];

    protected function casts(): array
    {
        return [
            ...array_fill_keys(array_keys(self::FACILITIES), 'boolean'),
            'name_needs_confirming' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'court_count' => 'integer',
            'court_hire_cents' => 'integer',
            'profile_tier' => 'integer',
            'photos' => 'array',
            'checked_at' => 'date',
            'enriched_at' => 'date',
        ];
    }

    /** Clubs that play here; the pivot's is_primary marks a club's main venue. */
    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class)->withPivot('is_primary')->withTimestamps();
    }

    public function fullAddress(): string
    {
        return collect([$this->street_address, $this->suburb, trim("{$this->state} {$this->postcode}")])->filter()->implode(', ');
    }

    public function mapUrl(): ?string
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return "https://www.google.com/maps/search/?api=1&query={$this->latitude},{$this->longitude}";
        }

        return $this->street_address ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($this->fullAddress()) : null;
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->whereLike('name', "%{$term}%")
            ->orWhereLike('suburb', "%{$term}%")
            ->orWhereLike('postcode', "{$term}%")
            ->orWhereLike('ref', "{$term}%")));
    }
}
