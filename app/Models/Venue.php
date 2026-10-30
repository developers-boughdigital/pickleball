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

    // Dropdown values, copied from the "Lists" tab of the directory spreadsheet.
    public const TYPES = ['Council outdoor', 'Council indoor', 'Leisure centre', 'Commercial indoor', 'Tennis club', 'School', 'Community hall', 'Private'];

    public const STATES = ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'];

    public const SETTINGS = ['Indoor', 'Outdoor', 'Both'];

    public const SURFACES = ['Acrylic hard court', 'Asphalt', 'Concrete', 'Synthetic grass', 'Timber', 'Sprung timber', 'Vinyl', 'Other'];

    public const COURT_LINES = ['Dedicated pickleball courts', 'Shared lines (tennis or other)', 'Both'];

    public const STATUSES = ['To check', 'Checked', 'Needs follow-up', 'Duplicate', 'Closed'];

    public const SOURCE_TYPES = [
        'Own website', 'Council website', 'Phone call', 'Email reply', 'Facebook or Instagram',
        'Pickleball Australia (with permission)', 'State association (with permission)', 'Open government data',
        'OpenStreetMap', 'Google Places (place ID only)', 'Self-submitted', 'Other', 'Pickleball Australia map (permission pending)',
    ];

    /** Yes/No/Unknown facilities kept in the attributes column. */
    public const FACILITIES = [
        'covered' => 'Covered',
        'nets_provided' => 'Nets provided',
        'free_to_use' => 'Free to use',
        'booking_required' => 'Booking required',
        'accessible' => 'Accessible',
        'toilets' => 'Toilets',
        'parking' => 'Parking',
    ];

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'court_count' => 'integer',
            'lights' => 'boolean',
            'attributes' => 'array',
            'photos' => 'array',
            'price_per_hour' => 'decimal:2',
            'checked_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Venue $venue) {
            $venue->code ??= static::nextCode();
        });

        static::saving(function (Venue $venue) {
            $venue->slug = $venue->isDirty('name') || ! $venue->slug ? $venue->uniqueSlug() : $venue->slug;
        });
    }

    /**
     * Fill from validated form or spreadsheet data (see VenueRequest). Facilities
     * and club codes go into the attributes column.
     *
     * @param  bool  $keepUnsent  leave fields that aren't in $data alone (an import with fewer columns)
     */
    public function fillFromForm(array $data, bool $keepUnsent = false): static
    {
        $attributes = $this->getAttribute('attributes') ?? [];

        foreach (array_keys(self::FACILITIES) as $key) {
            if (! $keepUnsent || array_key_exists($key, $data['facilities'] ?? [])) {
                $attributes[$key] = isset($data['facilities'][$key]) ? (bool) $data['facilities'][$key] : null;
            }
        }

        if (! $keepUnsent || array_key_exists('club_codes', $data)) {
            $attributes['club_codes'] = Str::of($data['club_codes'] ?? '')->upper()->explode(',')->map(fn ($code) => trim($code))->filter()->unique()->values()->all();
        }

        return $this->fill([...collect($data)->except('facilities', 'club_codes')->all(), 'attributes' => $attributes]);
    }

    /** V0001, V0002, ... continuing after the highest code in use. */
    public static function nextCode(): string
    {
        $highest = static::withTrashed()->where('code', 'like', 'V%')->pluck('code')
            ->map(fn (string $code) => (int) substr($code, 1))
            ->max() ?? 0;

        return sprintf('V%04d', $highest + 1);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** "Pickledome", "pickledome-2", ... */
    private function uniqueSlug(): string
    {
        $base = Str::slug($this->name) ?: 'venue';
        $taken = static::withTrashed()
            ->where('slug', 'like', "{$base}%")
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->pluck('slug')
            ->flip();

        $slug = $base;
        for ($i = 2; $taken->has($slug); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'Checked' => 'badge-success',
            'To check', 'Needs follow-up' => 'badge-pending',
            default => 'badge-muted',
        };
    }

    /** true / false / null (unknown) */
    public function facility(string $key): ?bool
    {
        return $this->getAttribute('attributes')[$key] ?? null;
    }

    /** @return list<string> */
    public function clubCodes(): array
    {
        return $this->getAttribute('attributes')['club_codes'] ?? [];
    }

    public function fullAddress(): string
    {
        return collect([$this->address, $this->suburb, trim("{$this->state} {$this->postcode}")])->filter()->implode(', ');
    }

    public function mapUrl(): ?string
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return "https://www.google.com/maps/search/?api=1&query={$this->latitude},{$this->longitude}";
        }

        return $this->address ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($this->fullAddress()) : null;
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->whereLike('name', "%{$term}%")
            ->orWhereLike('suburb', "%{$term}%")
            ->orWhereLike('postcode', "{$term}%")
            ->orWhereLike('code', "{$term}%")));
    }
}
