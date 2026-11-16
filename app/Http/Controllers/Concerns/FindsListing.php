<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Club;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;

/** For routes like /claim/{kind}/{slug}, where kind is "club" or "venue". */
trait FindsListing
{
    protected function findListing(string $kind, string $slug): Club|Venue
    {
        $model = match ($kind) {
            'club' => Club::class,
            'venue' => Venue::class,
            default => abort(404),
        };

        return $model::where('slug', $slug)->firstOrFail();
    }

    protected function publicUrl(Model $listing): string
    {
        return $listing instanceof Club ? route('explore.club', $listing) : route('explore.venue', $listing);
    }
}
