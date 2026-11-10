<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\Locality;
use App\Models\Venue;
use App\Support\Area;
use App\Support\Geography;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * How complete the four location levels are (state › city › council › suburb)
 * and which listings still need fixing, so every venue lands on a location page.
 */
class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $missing = $request->query('missing');
        $type = $request->query('type') === 'clubs' ? 'clubs' : 'venues';
        $model = $type === 'clubs' ? Club::class : Venue::class;

        $needsFix = $model::query()
            ->where(fn ($query) => match ($missing) {
                'city' => $query->whereNull('city'),
                'council' => $query->whereNull('council'),
                'postcode' => $query->whereNull('postcode'),
                default => $query->whereNull('state')->orWhereNull('city')->orWhereNull('council')->orWhereNull('suburb'),
            })
            ->orderBy('state')->orderBy('name')
            ->paginate(25, ['id', 'slug', 'ref', 'name', 'suburb', 'postcode', 'state', 'city', 'council'])
            ->withQueryString();

        // A postcode we can't find usually means a typo in the data (3014 for 3104).
        $unknownPostcodes = $model::query()->whereNotNull('postcode')
            ->whereNotIn('postcode', Locality::query()->select('postcode'))
            ->pluck('postcode')->unique()->values();

        $cities = Venue::query()->whereNotNull('city')
            ->groupBy('state', 'city')
            ->selectRaw('state, city, count(*) as total, count(council) as with_council')
            ->orderByDesc('total')->limit(12)->toBase()->get()
            ->map(fn (object $row) => (object) [...(array) $row, 'url' => Area::make($row->state, $row->city)->url(['drafts' => 1])]);

        return view('admin.locations.index', [
            'coverage' => Geography::coverage(),
            'localities' => Locality::count(),
            'needsFix' => $needsFix,
            'unknownPostcodes' => $unknownPostcodes,
            'cities' => $cities,
            'type' => $type,
            'missing' => $missing,
        ]);
    }

    public function fill(Request $request): RedirectResponse
    {
        if (! Locality::exists()) {
            return back()->with('status', 'The suburb lookup is empty. Run `php artisan localities:import` on the server first.');
        }

        $filled = Geography::fillDirectory();
        $message = "Filled city and council on {$filled['venues']} venues and {$filled['clubs']} clubs from their postcodes.";

        ActivityLog::record('directory.locations_filled', $message);

        return back()->with('status', $message);
    }
}
