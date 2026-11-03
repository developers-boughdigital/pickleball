<?php

namespace App\Models;

use App\Models\Concerns\DirectoryListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Club extends Model
{
    use DirectoryListing, HasUuids, SoftDeletes;

    public const REF_PREFIX = 'C';

    // Pipe-separated in clubs.csv ("new|beginner"), stored as JSON arrays.
    public const LEVELS = [
        'new' => 'New',
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
        'open' => 'Open',
    ];

    public const BOOKING_METHODS = [
        'walk_up' => 'Walk up',
        'book_online' => 'Book online',
        'members_only' => 'Members only',
        'walk_up_or_book' => 'Walk up or book',
        'contact_club' => 'Contact the club',
    ];

    /** Yes/No/Unknown columns, in the order clubs.csv lists them. */
    public const FEATURES = [
        'beginners_welcome' => 'Beginners welcome',
        'juniors' => 'Juniors',
        'seniors' => 'Seniors',
        'womens_sessions' => 'Women’s sessions',
        'equipment_provided' => 'Equipment provided',
        'coaching_available' => 'Coaching available',
        'come_and_try' => 'Come and try',
        'dupr_play' => 'DUPR play',
        'pa_affiliated' => 'PA affiliated',
    ];

    /** Fees kept in whole cents. */
    public const FEES = [
        'membership_fee_cents' => 'Membership fee (per year)',
        'casual_fee_cents' => 'Casual fee (per session)',
        'court_hire_cents' => 'Court hire (per hour)',
    ];

    public const SOCIALS = ['website' => 'Website', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'];

    public const LISTS = [
        'booking_method' => self::BOOKING_METHODS,
        'record_status' => self::RECORD_STATUSES,
        'claim_status' => self::CLAIM_STATUSES,
        'status' => self::STATUSES,
    ];

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected function casts(): array
    {
        return [
            ...array_fill_keys(array_keys(self::FEATURES), 'boolean'),
            ...array_fill_keys(array_keys(self::FEES), 'integer'),
            'levels' => 'array',
            'session_types' => 'array',
            'year_established' => 'integer',
            'member_count' => 'integer',
            'profile_tier' => 'integer',
            'checked_at' => 'date',
            'enriched_at' => 'date',
        ];
    }

    /** Venues the club plays at; the pivot's is_primary marks its main venue. */
    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class)->withPivot('is_primary')->withTimestamps();
    }

    public function primaryVenue(): ?Venue
    {
        return $this->venues->firstWhere('pivot.is_primary', true);
    }

    /** "Beginner, Advanced" */
    public function levelNames(): string
    {
        return collect($this->levels ?? [])->map(fn (string $level) => self::LEVELS[$level] ?? $level)->implode(', ');
    }

    public function location(): string
    {
        return collect([$this->suburb, trim("{$this->state} {$this->postcode}")])->filter()->implode(', ');
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->whereLike('name', "%{$term}%")
            ->orWhereLike('suburb', "%{$term}%")
            ->orWhereLike('postcode', "{$term}%")
            ->orWhereLike('email', "%{$term}%")
            ->orWhereLike('ref', "{$term}%")));
    }
}
