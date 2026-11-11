<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Locality;
use App\Models\Venue;
use App\Support\Area;
use App\Support\SampleCoaches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Location pages, the main way in from Google: /play/, /play/{state}/,
 * /play/{state}/{city}/, /play/{state}/{city}/{council}/ and
 * /play/{state}/{city}/{council}/{suburb}/ (brief: "Location page, the most
 * important template"). Only published listings show; admins can add ?drafts=1.
 */
class LocationController extends Controller
{
    /** A suburb page also lists venues this close to the suburb's centre. */
    private const SUBURB_RADIUS_KM = 5;

    /** Under this many venues an area is "thin" and the page offers the nearest ones. */
    private const THIN_AREA = 3;

    public function hub(Request $request): View
    {
        return $this->page($request, Area::australia());
    }

    /**
     * The "Find a game near you" box. A state, city, council, suburb or postcode
     * goes to its location page; anything else (a club name) searches the lists.
     */
    public function find(Request $request): RedirectResponse
    {
        $keep = $request->boolean('drafts') ? ['drafts' => 1] : [];

        if ($request->filled('lat')) {
            return redirect()->route('explore.clubs', $request->only('lat', 'lng', 'drafts'));
        }

        $text = $request->string('q')->squish()->limit(100, '')->value();
        $area = $text === '' ? Area::australia() : $this->placeNamed($text);

        return $area
            ? redirect()->to($area->url($keep))
            : redirect()->route('explore.clubs', ['q' => $text, ...$keep]);
    }

    private function placeNamed(string $text): ?Area
    {
        // "Greensborough VIC" or "Banyule, Vic": a state on the end narrows it down.
        $state = null;
        if (preg_match('/^(.+?)[\s,]+([a-z]{2,3})$/i', $text, $parts) && in_array(strtoupper($parts[2]), Venue::STATES)) {
            [$text, $state] = [trim($parts[1], ' ,'), strtoupper($parts[2])];
        }

        $code = strtoupper($text);
        $named = array_search(mb_strtolower($text), array_map('mb_strtolower', Venue::STATE_NAMES));
        if (! $state && (in_array($code, Venue::STATES) || $named)) {
            return Area::make($named ?: $code);
        }

        if (preg_match('/^\d{4}$/', $text)) {
            $place = Locality::query()->where('postcode', $text)->whereNotNull('city')->orderBy('suburb')->first();

            return $place ? Area::make($place->state, $place->city, $place->council, $place->suburb) : null;
        }

        // Biggest first, so "Melbourne" is the city rather than the suburb.
        $name = mb_strtolower($text);
        foreach (['city', 'council', 'suburb'] as $level) {
            foreach ([Venue::class, Club::class, Locality::class] as $model) {
                $match = $model::query()->whereRaw("lower({$level}) = ?", [$name])->whereNotNull('city')
                    ->when($state, fn (Builder $query) => $query->where('state', $state))
                    ->first(['state', 'city', 'council', 'suburb']);
                if ($match && ($level === 'city' || $match->council)) {
                    return Area::make($match->state, $match->city, $level === 'city' ? null : $match->council, $level === 'suburb' ? $match->suburb : null);
                }
            }
        }

        return null;
    }

    public function show(Request $request, string $state, ?string $city = null, ?string $council = null, ?string $suburb = null): View
    {
        $area = Area::fromSlugs($state, $city, $council, $suburb);
        abort_unless($area, 404);

        return $this->page($request, $area);
    }

    private function page(Request $request, Area $area): View
    {
        $drafts = $request->boolean('drafts') && $request->user()?->canAny(['clubs.view', 'venues.view']);
        $centre = $area->centre();
        $nearbyVenueIds = $area->level() === 'suburb' && $centre ? $this->venueIdsNear($centre, self::SUBURB_RADIUS_KM, $drafts) : collect();

        // A suburb page takes in venues within 5km, and the clubs that play at them.
        $venues = fn () => Venue::query()->visible($drafts)->where(fn (Builder $query) => $area->scope($query)
            ->when($nearbyVenueIds->isNotEmpty(), fn (Builder $query) => $query->orWhereIn('venues.id', $nearbyVenueIds)));
        $clubs = fn () => Club::query()->visible($drafts)->where(fn (Builder $query) => $area->scope($query)
            ->when($nearbyVenueIds->isNotEmpty(), fn (Builder $query) => $query->orWhereHas('venues', fn (Builder $query) => $query->whereIn('venues.id', $nearbyVenueIds))));
        $visibleVenues = ['venues' => fn ($query) => $query->visible($drafts)->orderByDesc('club_venue.is_primary')];

        $venueCount = $venues()->count();
        $showDistance = $centre && in_array($area->level(), ['council', 'suburb']);

        return view('play.location', [
            'area' => $area,
            'place' => $area->name(),
            'drafts' => $drafts,
            'canPreview' => (bool) $request->user()?->canAny(['clubs.view', 'venues.view']),
            'centre' => $centre,
            'radius' => $nearbyVenueIds->isNotEmpty() ? self::SUBURB_RADIUS_KM : null,
            'stats' => [
                'venues' => $venueCount,
                'clubs' => $clubs()->count(),
                'indoor' => $venues()->whereIn('indoor_outdoor', ['indoor', 'both'])->count(),
                'outdoor' => $venues()->whereIn('indoor_outdoor', ['outdoor', 'both'])->count(),
                'free' => $venues()->where('free_to_use', true)->count(),
                'beginners' => $clubs()->where('beginners_welcome', true)->count(),
                'equipment' => $clubs()->where('equipment_provided', true)->count(),
                'withSessions' => $clubs()->whereNotNull('session_times_text')->count(),
                'fees' => $clubs()->whereNotNull('casual_fee_cents')->selectRaw('min(casual_fee_cents) as low, max(casual_fee_cents) as high')->toBase()->first(),
            ],
            'pins' => $venues()->whereNotNull('latitude')->whereNotNull('longitude')
                ->withExists(['clubs' => fn (Builder $query) => $query->visible($drafts)])
                ->get(['id', 'slug', 'name', 'suburb', 'latitude', 'longitude']),
            'children' => $this->children($area, $drafts),
            'nearby' => $this->nearby($area, $centre, $drafts),
            'sessions' => $clubs()->whereNotNull('session_times_text')->with($visibleVenues)
                ->orderByDesc('beginners_welcome')->orderBy('name')->limit(4)->get(),
            'venues' => $this->withDistance($venues(), $showDistance ? $centre : null)
                ->withCount(['clubs' => fn (Builder $query) => $query->visible($drafts)])
                ->when(! $showDistance, fn (Builder $query) => $query->orderByRaw('court_count desc nulls last'))
                ->orderBy('name')->limit(10)->get(),
            'biggest' => $venues()->whereNotNull('court_count')->orderByDesc('court_count')->limit(3)->get(['name', 'suburb']),
            'clubs' => $clubs()->withCount(['venues' => fn (Builder $query) => $query->visible($drafts)])->with($visibleVenues)
                ->orderByDesc('profile_tier')->orderBy('name')->limit(6)->get(),
            // Thin area: the nearest venues outside it, so the page is still useful.
            'nearest' => $venueCount < self::THIN_AREA && $centre
                ? $this->withDistance(Venue::query()->visible($drafts)->whereNotIn('venues.id', $venues()->select('venues.id')), $centre)->whereNotNull('latitude')->limit(5)->get()
                : collect(),
            'coaches' => SampleCoaches::all()->take(3),
            'showDistance' => $showDistance,
        ]);
    }

    /**
     * The next level down with what's listed in each: cities in a state, councils
     * in a city, suburbs in a council. States across Australia.
     *
     * @return Collection<int, object{area: Area, venues: int, clubs: int}>
     */
    private function children(Area $area, bool $drafts): Collection
    {
        $level = $area->childLevel();
        if (! $level) {
            return collect();
        }

        $count = fn (string $model) => $area->scope($model::query()->visible($drafts))->whereNotNull($level)
            ->groupBy($level)->selectRaw("{$level} as name, count(*) as total")->pluck('total', 'name');
        $venues = $count(Venue::class);
        $clubs = $count(Club::class);

        return $venues->keys()->merge($clubs->keys())->unique()
            ->map(fn (string $name) => (object) [
                'area' => $area->with($level, $name),
                'venues' => (int) ($venues[$name] ?? 0),
                'clubs' => (int) ($clubs[$name] ?? 0),
            ])
            ->sortByDesc(fn (object $row) => [$row->venues, $row->clubs])
            ->values();
    }

    /**
     * Places beside this one (same parent), nearest first when we know where this
     * area is. A state lists the other states.
     *
     * @return Collection<int, object{area: Area, venues: int}>
     */
    private function nearby(Area $area, ?array $centre, bool $drafts): Collection
    {
        $parent = $area->parent();
        $level = $area->level();
        if (! $parent) {
            return collect();
        }

        $rows = $parent->scope(Venue::query()->visible($drafts))->whereNotNull($level)
            ->whereRaw("lower({$level}) <> ?", [mb_strtolower($area->{$level})])
            ->groupBy($level)
            ->selectRaw("{$level} as name, count(*) as total, avg(latitude) as lat, avg(longitude) as lng")
            ->toBase()->get();

        return $rows
            ->map(fn (object $row) => (object) [
                'area' => $parent->with($level, $row->name),
                'venues' => (int) $row->total,
                'distance' => $centre && $row->lat !== null
                    ? ($row->lat - $centre['lat']) ** 2 + (($row->lng - $centre['lng']) * cos(deg2rad($centre['lat']))) ** 2
                    : INF,
            ])
            ->sortBy(fn (object $row) => [$row->distance, -$row->venues])
            ->take(8)
            ->values();
    }

    /** @return Collection<int, string> */
    private function venueIdsNear(array $centre, int $km, bool $drafts): Collection
    {
        // Written into the SQL as a number: SQLite compares a bound value as text.
        $squared = sprintf('%.10F', ($km / 111.32) ** 2);

        return Venue::query()->visible($drafts)->whereNotNull('latitude')
            ->whereRaw($this->distanceSql($centre).' <= '.$squared)
            ->pluck('id');
    }

    /** Orders by distance from a point and adds a `distance` the views turn into km. */
    private function withDistance(Builder $query, ?array $centre): Builder
    {
        if (! $centre) {
            return $query;
        }

        return $query->select('venues.*')
            ->selectRaw('coalesce('.$this->distanceSql($centre).', '.Venue::UNKNOWN_DISTANCE.') as distance')
            ->orderBy('distance');
    }

    /** Squared distance in "degrees", as on the lists: arithmetic only, so it runs on any database. */
    private function distanceSql(array $centre): string
    {
        $lat = (float) $centre['lat'];
        $lng = (float) $centre['lng'];
        $scale = cos(deg2rad($lat));

        return "((latitude - {$lat}) * (latitude - {$lat}) + (longitude - {$lng}) * {$scale} * (longitude - {$lng}) * {$scale})";
    }
}
