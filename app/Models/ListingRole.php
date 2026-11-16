<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Someone who runs a club or manages a venue. Approving a claim adds the first one. */
class ListingRole extends Model
{
    public const CLUB_ROLES = ['admin' => 'Club admin', 'host' => 'Host', 'coach' => 'Coach', 'treasurer' => 'Treasurer'];

    public const VENUE_ROLES = ['manager' => 'Venue manager'];

    /** Roles that can edit the listing. Hosts, coaches and treasurers get their own tools later (step 15). */
    public const EDITORS = ['admin', 'manager'];

    protected $guarded = ['id'];

    public function listing(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return (self::CLUB_ROLES + self::VENUE_ROLES)[$this->role] ?? ucfirst($this->role);
    }
}
