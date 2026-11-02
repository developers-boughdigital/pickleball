<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Venue extends Model
{
    use HasUuids, SoftDeletes;

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

    public const STATES = ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'];

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

    public const RECORD_STATUSES = [
        'to_check' => 'To check',
        'checked' => 'Checked',
        'needs_follow_up' => 'Needs follow-up',
        'duplicate' => 'Duplicate',
        'closed' => 'Closed',
    ];

    public const CLAIM_STATUSES = [
        'not_contacted' => 'Not contacted',
        'invited' => 'Invited',
        'claimed' => 'Claimed',
        'profile_completed' => 'Profile completed',
        'removal_requested' => 'Removal requested',
        'removed' => 'Removed',
    ];

    /** Publishing state. Only checked venues can be published. */
    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

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

    protected $attributes = [
        'name_needs_confirming' => false,
        'record_status' => 'to_check',
        'claim_status' => 'not_contacted',
        'profile_tier' => 1,
        'status' => 'draft',
    ];

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

    protected static function booted(): void
    {
        static::creating(function (Venue $venue) {
            $venue->ref ??= static::nextRef();
            $venue->slug = $venue->uniqueSlug($venue->slug ?: $venue->name);
        });

        static::saving(function (Venue $venue) {
            // A slug is set once; links to the venue must keep working after a rename.
            if ($venue->exists && $venue->isDirty('slug')) {
                $venue->slug = $venue->getOriginal('slug');
            }

            if ($venue->status === 'published' && $venue->record_status !== 'checked') {
                $venue->status = 'draft';
            }
        });
    }

    /** V0001, V0002, ... continuing after the highest ref in use. */
    public static function nextRef(): string
    {
        $highest = static::withTrashed()->where('ref', 'like', 'V%')->pluck('ref')
            ->map(fn (string $ref) => (int) substr($ref, 1))
            ->max() ?? 0;

        return sprintf('V%04d', $highest + 1);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** "pickledome", "pickledome-2", ... */
    private function uniqueSlug(string $text): string
    {
        $base = Str::slug($text) ?: 'venue';
        $taken = static::withTrashed()->where('slug', 'like', "{$base}%")->pluck('slug')->flip();

        $slug = $base;
        for ($i = 2; $taken->has($slug); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /** The admin label for a coded column, e.g. "leisure_centre" -> "Leisure centre". */
    public function label(string $field): ?string
    {
        $value = $this->getAttribute($field);

        return $value === null ? null : (self::LISTS[$field][$value] ?? $value);
    }

    public function statusBadge(): string
    {
        return $this->status === 'published' ? 'badge-success' : 'badge-muted';
    }

    public function recordStatusBadge(): string
    {
        return match ($this->record_status) {
            'checked' => 'badge-success',
            'to_check', 'needs_follow_up' => 'badge-pending',
            default => 'badge-muted',
        };
    }

    public function courtHire(): ?string
    {
        return $this->court_hire_cents === null ? null : '$'.number_format($this->court_hire_cents / 100, 2);
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
