<?php

namespace App\Models;

use App\Notifications\ClaimDecided;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "This is my club": someone asking to run a club or venue listing. A platform
 * admin approves it (the claimant becomes the club admin or venue manager and the
 * listing is "claimed") or rejects it with a reason.
 */
class ListingClaim extends Model
{
    use HasUuids;

    public const STATUSES = ['pending' => 'Waiting for review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn'];

    /** Offered on the form; anything else can be typed. */
    public const SUGGESTED_ROLES = [
        'club' => ['President', 'Secretary', 'Treasurer', 'Committee member', 'Coach', 'Organiser'],
        'venue' => ['Venue manager', 'Owner', 'Centre staff', 'Council officer'],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function claimable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    /** "club" or "venue". */
    public function kind(): string
    {
        return $this->claimable_type === Club::class ? 'club' : 'venue';
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            'pending' => 'badge-pending',
            default => 'badge-muted',
        };
    }

    /**
     * A hint for the reviewer, not proof: the claimant's email is on the same
     * domain as the listing's website or email (sam@banyulepickleball.org.au).
     */
    public function emailMatchesListing(): bool
    {
        $domain = fn (?string $value) => $value ? Str::of($value)->lower()->afterLast('@')->replaceMatches('~^https?://~', '')->before('/')->replaceStart('www.', '')->value() : null;
        $mine = $domain($this->user?->email);
        $free = ['gmail.com', 'outlook.com', 'hotmail.com', 'yahoo.com', 'yahoo.com.au', 'icloud.com', 'bigpond.com', 'live.com', 'live.com.au', 'optusnet.com.au'];

        if (! $mine || in_array($mine, $free)) {
            return false;
        }

        return collect([$this->claimable?->website, $this->claimable?->email])->map($domain)->filter()
            ->contains(fn (string $theirs) => $theirs === $mine || str_ends_with($mine, ".{$theirs}") || str_ends_with($theirs, ".{$mine}"));
    }

    /**
     * Make the claimant the club admin (or venue manager), mark the listing
     * claimed, and turn down anyone else waiting on the same listing.
     */
    public function approve(User $admin): void
    {
        DB::transaction(function () use ($admin) {
            $this->update(['status' => 'approved', 'decided_by' => $admin->id, 'decided_at' => now(), 'reject_reason' => null]);

            $listing = $this->claimable;
            $listing->listingRoles()->firstOrCreate(
                ['user_id' => $this->user_id],
                ['role' => $this->kind() === 'club' ? 'admin' : 'manager', 'granted_by' => $admin->id],
            );
            $listing->forceFill([
                'listing_status' => 'claimed',
                // Keep the outreach column in step, unless it's further along already.
                'claim_status' => in_array($listing->claim_status, ['not_contacted', 'invited']) ? 'claimed' : $listing->claim_status,
            ])->save();

            $others = $listing->claims()->pending()->whereKeyNot($this->getKey())->get();
            foreach ($others as $other) {
                $other->reject($admin, 'Someone else’s claim for this listing was approved. Ask them to add you from the club’s admin.', notify: true);
            }
        });

        $this->notifyClaimant();
    }

    public function reject(User $admin, string $reason, bool $notify = true): void
    {
        DB::transaction(function () use ($admin, $reason) {
            $this->update(['status' => 'rejected', 'decided_by' => $admin->id, 'decided_at' => now(), 'reject_reason' => $reason]);
            $this->claimable?->refreshListingStatus();
        });

        $notify && $this->notifyClaimant();
    }

    /** The claimant takes it back before anyone has looked at it. */
    public function withdraw(): void
    {
        $this->update(['status' => 'withdrawn']);
        $this->claimable?->refreshListingStatus();
    }

    private function notifyClaimant(): void
    {
        // A mail server being down mustn't undo the decision.
        rescue(fn () => $this->user?->notify(new ClaimDecided($this)));
    }
}
