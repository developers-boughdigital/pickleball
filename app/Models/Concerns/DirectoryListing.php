<?php

namespace App\Models\Concerns;

use App\Models\Locality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * What venues and clubs share as listings from the directory import pack:
 * a ref (V0001, C0001) that imports match on, a slug that is set once, the
 * VA's record status, and a publishing status. Only checked listings can be
 * published (files/IMPORT_GUIDE.md, rule 3).
 *
 * The using model defines REF_PREFIX and LISTS (column => [code => label]).
 */
trait DirectoryListing
{
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

    /** Publishing state. Only checked listings can be published. */
    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

    /** Sort value for listings without coordinates, so they come last on every database. */
    public const UNKNOWN_DISTANCE = 999999;

    public const STATES = ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'];

    public const STATE_NAMES = [
        'ACT' => 'Australian Capital Territory',
        'NSW' => 'New South Wales',
        'NT' => 'Northern Territory',
        'QLD' => 'Queensland',
        'SA' => 'South Australia',
        'TAS' => 'Tasmania',
        'VIC' => 'Victoria',
        'WA' => 'Western Australia',
    ];

    public function initializeDirectoryListing(): void
    {
        $this->attributes += [
            'record_status' => 'to_check',
            'claim_status' => 'not_contacted',
            'profile_tier' => 1,
            'status' => 'draft',
        ];
    }

    protected static function bootDirectoryListing(): void
    {
        static::creating(function (self $listing) {
            $listing->ref ??= static::nextRef();
            $listing->slug = $listing->uniqueSlug($listing->slug ?: $listing->name);
        });

        static::saving(function (self $listing) {
            // A slug is set once; links to the listing must keep working after a rename.
            if ($listing->exists && $listing->isDirty('slug')) {
                $listing->slug = $listing->getOriginal('slug');
            }

            if ($listing->status === 'published' && $listing->record_status !== 'checked') {
                $listing->status = 'draft';
            }

            // Location pages need city and council; fill them from the postcode when they're blank.
            if ($listing->isDirty(['postcode', 'suburb', 'city', 'council']) || ! $listing->exists) {
                Locality::fillListing($listing);
            }
        });
    }

    /** V0001, V0002, ... continuing after the highest ref in use. */
    public static function nextRef(): string
    {
        $highest = static::withTrashed()->where('ref', 'like', static::REF_PREFIX.'%')->pluck('ref')
            ->map(fn (string $ref) => (int) substr($ref, strlen(static::REF_PREFIX)))
            ->max() ?? 0;

        return sprintf('%s%04d', static::REF_PREFIX, $highest + 1);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** "pickledome", "pickledome-2", ... */
    private function uniqueSlug(string $text): string
    {
        $base = Str::slug($text) ?: Str::lower(class_basename($this));
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

        return $value === null ? null : (static::LISTS[$field][$value] ?? $value);
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

    /** What the public site may show: published listings, or everything in an admin preview. */
    public function scopeVisible(Builder $query, bool $drafts = false): void
    {
        $query->when(! $drafts, fn (Builder $query) => $query->where($this->qualifyColumn('status'), 'published'));
    }

    /** Distance in km from the squared-degrees value the explore page sorts by. */
    public function distanceKm(): ?float
    {
        $distance = $this->getAttribute('distance');

        return $distance === null || $distance >= self::UNKNOWN_DISTANCE ? null : sqrt((float) $distance) * 111.32;
    }

    /** "$13.00" from a *_cents column. */
    public function money(string $field): ?string
    {
        $cents = $this->getAttribute($field);

        return $cents === null ? null : '$'.number_format($cents / 100, 2);
    }
}
