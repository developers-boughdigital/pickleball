<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A visitor telling us about a court we don't list, or a listing that's wrong. */
class ListingReport extends Model
{
    public const KINDS = [
        'new_court' => 'New court',
        'incorrect_details' => 'Incorrect details',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
