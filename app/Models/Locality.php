<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A suburb and postcode with the council and city or region it belongs to.
 * See database/data/localities.csv and the localities:import command.
 */
class Locality extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float'];
    }

    /**
     * City or region from the ABS SA4 name. Capital cities take in their whole
     * metro area ("Melbourne - North East" is Melbourne); elsewhere the region
     * name is kept, without a leading state name.
     */
    public static function cityFor(string $state, ?string $sa4): ?string
    {
        if ($state === 'ACT') {
            return 'Canberra';
        }

        $sa4 = trim((string) $sa4);
        if ($sa4 === '' || $sa4 === 'Other Territories') {
            return null;
        }

        foreach (['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide'] as $capital) {
            if (str_starts_with($sa4, $capital.' -') || $sa4 === $capital.' Inner City') {
                return $capital;
            }
        }

        // The rest of Greater Brisbane.
        if (in_array($sa4, ['Ipswich', 'Logan - Beaudesert', 'Moreton Bay - North', 'Moreton Bay - South'])) {
            return 'Brisbane';
        }

        return match ($sa4) {
            'Hunter Valley exc Newcastle' => 'Hunter Valley',
            'Latrobe - Gippsland' => 'Gippsland',
            'Western Australia - Wheat Belt' => 'Wheatbelt',
            // "Barossa - Yorke - Mid North" reads as "Barossa, Yorke and Mid North".
            default => Str::of($sa4)->after(Venue::STATE_NAMES[$state].' - ')->explode(' - ')->join(', ', ' and '),
        };
    }

    /** The ACT has no councils, so its districts (Belconnen, Tuggeranong) stand in. */
    public static function councilFor(string $state, ?string $lga, ?string $sa3): ?string
    {
        if ($state === 'ACT') {
            return $sa3 ?: null;
        }

        // "Kingston (Vic.)" tells councils with the same name apart across states; the state is in the URL already.
        $lga = trim(preg_replace('/\s*\((?:ACT|NSW|NT|Qld|SA|Tas|Vic|WA)\.?\)$/i', '', (string) $lga));

        return $lga === '' || str_starts_with($lga, 'Unincorp') ? null : $lga;
    }

    /** "ST HELENA" → "St Helena", "MCKINNON" → "McKinnon". */
    public static function suburbName(string $suburb): string
    {
        return preg_replace_callback('/\bMc(\p{Ll})/u', fn ($m) => 'Mc'.mb_strtoupper($m[1]), Str::title(mb_strtolower(trim($suburb))));
    }

    /**
     * Fills an empty city and council on a venue or club from its postcode and
     * suburb. Never overwrites what someone typed. Returns whether it changed.
     */
    public static function fillListing(Model $listing): bool
    {
        if (filled($listing->city) && filled($listing->council)) {
            return false;
        }

        $match = static::match($listing->postcode, $listing->suburb, $listing->state);
        if (! $match) {
            return false;
        }

        $listing->city = $listing->city ?: $match->city;
        $listing->council = $listing->council ?: $match->council;

        return $listing->isDirty(['city', 'council']);
    }

    /**
     * The locality for a listing: an exact suburb and postcode match, or failing
     * that the most common council and city for the postcode.
     */
    public static function match(?string $postcode, ?string $suburb, ?string $state = null): ?self
    {
        if (blank($postcode)) {
            return null;
        }

        $postcode = str_pad(trim($postcode), 4, '0', STR_PAD_LEFT);
        $query = static::query()->where('postcode', $postcode)->when($state, fn ($query) => $query->where('state', $state));

        if (filled($suburb) && $exact = (clone $query)->whereRaw('upper(suburb) = ?', [mb_strtoupper(trim($suburb))])->first()) {
            return $exact;
        }

        return $query->whereNotNull('council')
            ->selectRaw('council, city, state, count(*) as total, avg(latitude) as latitude, avg(longitude) as longitude')
            ->groupBy('council', 'city', 'state')
            ->orderByDesc('total')
            ->first();
    }
}
