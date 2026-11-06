<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Venue;
use App\Support\SampleCoaches;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The public directory: an area page ("Pickleball in Victoria"), full club and
 * venue lists, the coach index, and a page per club and venue.
 *
 * Only published listings are shown (files/IMPORT_GUIDE.md, rule 3). Admins can
 * add ?drafts=1 to preview everything before it goes live.
 */
class ExploreController extends Controller
{
    private const PER_PAGE = 24;

    public function index(Request $request): View|RedirectResponse
    {
        // Links from before the area page (/explore?tab=venues&q=…) went straight to a list.
        if ($request->hasAny(['tab', 'q', 'lat'])) {
            return redirect()->route($request->query('tab') === 'venues' ? 'explore.venues' : 'explore.clubs', $request->except('tab'));
        }

        $drafts = $this->showsDrafts($request);
        $state = $this->state($request);
        $area = $state ? $this->area($request) : null;

        $venues = fn () => Venue::query()->visible($drafts)->inArea($state, $area);
        $clubs = fn () => Club::query()->visible($drafts)->inArea($state, $area);
        $clubCounts = ['venues' => fn (Builder $query) => $query->visible($drafts)];

        return view('explore.index', [
            'state' => $state,
            'area' => $area,
            'place' => $area ?? ($state ? Venue::STATE_NAMES[$state] : 'Australia'),
            'drafts' => $drafts,
            'canPreview' => $this->canPreview($request),
            'stats' => [
                'venues' => $venues()->count(),
                'clubs' => $clubs()->count(),
                'indoor' => $venues()->whereIn('indoor_outdoor', ['indoor', 'both'])->count(),
                'outdoor' => $venues()->whereIn('indoor_outdoor', ['outdoor', 'both'])->count(),
                'free' => $venues()->where('free_to_use', true)->count(),
                'beginners' => $clubs()->where('beginners_welcome', true)->count(),
                'equipment' => $clubs()->where('equipment_provided', true)->count(),
                'fees' => $clubs()->whereNotNull('casual_fee_cents')->selectRaw('min(casual_fee_cents) as low, max(casual_fee_cents) as high')->toBase()->first(),
            ],
            'pins' => $venues()->whereNotNull('latitude')->whereNotNull('longitude')
                ->withExists(['clubs' => fn (Builder $query) => $query->visible($drafts)])
                ->get(['id', 'slug', 'name', 'suburb', 'latitude', 'longitude']),
            'areas' => $area ? collect() : $this->areas($state, $drafts),
            'sessions' => $clubs()->whereNotNull('session_times_text')
                ->with(['venues' => fn ($query) => $query->visible($drafts)->orderByDesc('club_venue.is_primary')])
                ->orderByDesc('beginners_welcome')->orderBy('name')
                ->limit(4)->get(),
            'venues' => $venues()->orderByRaw('court_count desc nulls last')->orderBy('name')->limit(8)->get(),
            'biggest' => $venues()->whereNotNull('court_count')->orderByDesc('court_count')->limit(3)->get(['name', 'suburb']),
            'clubs' => $clubs()->withCount($clubCounts)
                ->with(['venues' => fn ($query) => $query->visible($drafts)->orderByDesc('club_venue.is_primary')])
                ->orderByDesc('profile_tier')->orderBy('name')
                ->limit(6)->get(),
            'coaches' => SampleCoaches::all()->take(3),
            'states' => $this->stateCounts($drafts),
        ]);
    }

    public function clubs(Request $request): View
    {
        return $this->list($request, 'clubs');
    }

    public function venues(Request $request): View
    {
        return $this->list($request, 'venues');
    }

    public function coaches(Request $request): View
    {
        $near = $this->near($request);
        $filters = [
            'where' => $request->string('where')->trim()->limit(60, '')->value() ?: null,
            'distance' => in_array((int) $request->query('distance'), [5, 10, 25, 50]) ? (int) $request->query('distance') : 25,
            'levels' => $this->pick($request, 'levels', Club::LEVELS),
            'types' => $this->pick($request, 'types', SampleCoaches::LESSON_TYPES),
            'focus' => $this->pick($request, 'focus', SampleCoaches::FOCUS),
            'price' => array_key_exists((string) $request->query('price'), SampleCoaches::PRICES) ? $request->query('price') : null,
            'times' => $this->pick($request, 'times', SampleCoaches::TIMES),
            'accreditation' => $this->pick($request, 'accreditation', SampleCoaches::ACCREDITATIONS),
            'wwcc' => $request->boolean('wwcc'),
            'sort' => in_array($request->query('sort'), ['nearest', 'price']) ? $request->query('sort') : 'best',
        ];

        $coaches = SampleCoaches::all()
            ->map(fn (array $coach) => $coach + ['km' => $near ? $this->km($near, $coach['lat'], $coach['lng']) : null])
            ->when($near, fn (Collection $coaches) => $coaches->where('km', '<=', $filters['distance']))
            ->when(! $near && $filters['where'], fn (Collection $coaches) => $coaches->filter(fn (array $coach) => str_contains(
                mb_strtolower("{$coach['suburb']} {$coach['state']} ".Venue::STATE_NAMES[$coach['state']]),
                mb_strtolower($filters['where']),
            )))
            ->filter(fn (array $coach) => (! $filters['levels'] || array_intersect($filters['levels'], $coach['levels']))
                && (! $filters['types'] || array_intersect($filters['types'], $coach['types']))
                && (! $filters['focus'] || array_intersect($filters['focus'], $coach['focus']))
                && (! $filters['times'] || array_intersect($filters['times'], $coach['times']))
                && (! $filters['accreditation'] || in_array($coach['accreditation'], $filters['accreditation']))
                && (! $filters['wwcc'] || $coach['wwcc'])
                && (! $filters['price'] || ($coach['price_cents'] >= SampleCoaches::PRICES[$filters['price']][1] && $coach['price_cents'] <= SampleCoaches::PRICES[$filters['price']][2])))
            ->sortBy(match ($filters['sort']) {
                'nearest' => fn (array $coach) => $coach['km'] ?? PHP_INT_MAX,
                'price' => 'price_cents',
                default => fn (array $coach) => -count(array_intersect($filters['focus'] ?: $coach['focus'], $coach['focus'])),
            })
            ->values();

        $drafts = $this->showsDrafts($request);

        return view('explore.coaches', [
            'coaches' => $coaches,
            'filters' => $filters,
            'near' => $near,
            'drafts' => $drafts,
            // Real data while coaches are placeholders: clubs that say they coach.
            'coachingClubs' => Club::query()->visible($drafts)
                ->where('coaching_available', true)
                ->when(! $near && $filters['where'], fn (Builder $query, string $where) => $query->search($where))
                ->when($filters['levels'], fn (Builder $query, array $levels) => $query->where(fn (Builder $query) => collect($levels)->each(fn ($level) => $query->orWhereJsonContains('levels', $level))))
                ->orderByRaw('coach_names_text is null')->orderBy('name')
                ->limit(6)->get(),
        ]);
    }

    public function club(Request $request, Club $club): View
    {
        $drafts = $this->showsDrafts($request);
        abort_unless($drafts || $club->status === 'published', 404);

        $club->load(['venues' => fn ($query) => $query->visible($drafts)->orderByDesc('club_venue.is_primary')->orderBy('name')]);

        return view('explore.club', ['club' => $club, 'drafts' => $drafts]);
    }

    public function venue(Request $request, Venue $venue): View
    {
        $drafts = $this->showsDrafts($request);
        abort_unless($drafts || $venue->status === 'published', 404);

        $venue->load(['clubs' => fn ($query) => $query->visible($drafts)->orderByDesc('club_venue.is_primary')->orderBy('name')]);

        return view('explore.venue', ['venue' => $venue, 'drafts' => $drafts]);
    }

    private function list(Request $request, string $tab): View
    {
        $drafts = $this->showsDrafts($request);
        $near = $this->near($request);
        $state = $this->state($request);

        $filters = [
            'q' => $request->string('q')->trim()->limit(100, '')->value() ?: null,
            'state' => $state,
            'area' => $state ? $this->area($request) : null,
            'level' => array_key_exists((string) $request->query('level'), Club::LEVELS) ? $request->query('level') : null,
            'beginners' => $request->boolean('beginners'),
            'coaching' => $request->boolean('coaching'),
            'equipment' => $request->boolean('equipment'),
            'come_and_try' => $request->boolean('come_and_try'),
            'max_fee' => in_array((int) $request->query('max_fee'), [10, 15, 20]) ? (int) $request->query('max_fee') : null,
            'setting' => array_key_exists((string) $request->query('setting'), Venue::SETTINGS) ? $request->query('setting') : null,
            'lights' => $request->boolean('lights'),
            'free' => $request->boolean('free'),
        ];

        $results = $tab === 'clubs' ? $this->clubResults($filters, $near, $drafts) : $this->venueResults($filters, $near, $drafts);

        return view('explore.list', [
            'tab' => $tab,
            'filters' => $filters,
            'near' => $near,
            'drafts' => $drafts,
            'canPreview' => $this->canPreview($request),
            'results' => $results->withQueryString(),
        ]);
    }

    private function clubResults(array $filters, ?array $near, bool $drafts): LengthAwarePaginator
    {
        $query = Club::query()
            ->visible($drafts)
            ->search($filters['q'])
            ->inArea($filters['state'], $filters['area'])
            ->withCount(['venues' => fn (Builder $query) => $query->visible($drafts)])
            ->when($filters['level'], fn (Builder $query, string $level) => $query->whereJsonContains('levels', $level))
            ->when($filters['beginners'], fn (Builder $query) => $query->where('beginners_welcome', true))
            ->when($filters['coaching'], fn (Builder $query) => $query->where('coaching_available', true))
            ->when($filters['equipment'], fn (Builder $query) => $query->where('equipment_provided', true))
            ->when($filters['come_and_try'], fn (Builder $query) => $query->where('come_and_try', true))
            ->when($filters['max_fee'], fn (Builder $query, int $dollars) => $query->where('casual_fee_cents', '<=', $dollars * 100));

        if ($near) {
            // A club is as close as the nearest venue it plays at.
            $query->addSelect(['distance' => Venue::query()
                ->join('club_venue', 'club_venue.venue_id', '=', 'venues.id')
                ->whereColumn('club_venue.club_id', 'clubs.id')
                ->selectRaw('coalesce(min('.$this->distanceSql($near).'), '.Venue::UNKNOWN_DISTANCE.')'),
            ])->orderBy('distance');
        }

        return $query->orderBy('name')->paginate(self::PER_PAGE);
    }

    private function venueResults(array $filters, ?array $near, bool $drafts): LengthAwarePaginator
    {
        $query = Venue::query()
            ->visible($drafts)
            ->search($filters['q'])
            ->inArea($filters['state'], $filters['area'])
            ->withCount(['clubs' => fn (Builder $query) => $query->visible($drafts)])
            ->when($filters['setting'], fn (Builder $query, string $setting) => $query->whereIn('indoor_outdoor', $setting === 'both' ? ['both'] : [$setting, 'both']))
            ->when($filters['lights'], fn (Builder $query) => $query->where('lights', true))
            ->when($filters['free'], fn (Builder $query) => $query->where('free_to_use', true));

        if ($near) {
            $query->selectRaw('coalesce('.$this->distanceSql($near).', '.Venue::UNKNOWN_DISTANCE.') as distance')->orderBy('distance');
        }

        return $query->orderBy('name')->paginate(self::PER_PAGE);
    }

    /**
     * The places to browse within the page's area: states across Australia, then
     * cities, council areas or suburbs within a state, whichever the data has.
     *
     * @return Collection<int, object{name: string, label: string, venues: int, clubs: int, kind: string}>
     */
    private function areas(?string $state, bool $drafts): Collection
    {
        if (! $state) {
            return $this->stateCounts($drafts)->map(fn (object $row) => (object) [...(array) $row, 'name' => $row->state, 'label' => Venue::STATE_NAMES[$row->state], 'kind' => 'state']);
        }

        $column = collect(['city', 'council', 'suburb'])->first(fn (string $column) => Venue::query()->visible($drafts)->where('state', $state)->whereNotNull($column)->exists()) ?? 'suburb';
        $count = fn (string $model) => $model::query()->visible($drafts)->where('state', $state)->whereNotNull($column)
            ->groupBy($column)->selectRaw("{$column} as name, count(*) as total")->pluck('total', 'name');

        $venues = $count(Venue::class);
        $clubs = $count(Club::class);

        return $venues->sortDesc()->take(8)->map(fn ($total, $name) => (object) [
            'name' => $name,
            'label' => $name,
            'venues' => (int) $total,
            'clubs' => (int) ($clubs[$name] ?? 0),
            'kind' => $column,
        ])->values();
    }

    /** @return Collection<int, object{state: string, venues: int, clubs: int}> */
    private function stateCounts(bool $drafts): Collection
    {
        $count = fn (string $model) => $model::query()->visible($drafts)->whereNotNull('state')
            ->groupBy('state')->selectRaw('state, count(*) as total')->pluck('total', 'state');

        $venues = $count(Venue::class);
        $clubs = $count(Club::class);

        return collect(Venue::STATES)
            ->map(fn (string $state) => (object) ['state' => $state, 'venues' => (int) ($venues[$state] ?? 0), 'clubs' => (int) ($clubs[$state] ?? 0)])
            ->filter(fn (object $row) => $row->venues || $row->clubs)
            ->sortByDesc('venues')
            ->values();
    }

    /**
     * Squared distance in "degrees", good enough to sort by and cheap enough to run
     * anywhere (no trig in SQL). The view turns it into kilometres.
     */
    private function distanceSql(array $near): string
    {
        $lat = (float) $near['lat'];
        $lng = (float) $near['lng'];
        $scale = cos(deg2rad($lat)); // a degree of longitude shrinks away from the equator

        return "((latitude - {$lat}) * (latitude - {$lat}) + (longitude - {$lng}) * {$scale} * (longitude - {$lng}) * {$scale})";
    }

    /** The same flat-earth sum in PHP, in km, for the placeholder coaches. */
    private function km(array $near, float $lat, float $lng): float
    {
        $scale = cos(deg2rad($near['lat']));

        return sqrt(($lat - $near['lat']) ** 2 + (($lng - $near['lng']) * $scale) ** 2) * 111.32;
    }

    /** @return array{lat: float, lng: float}|null */
    private function near(Request $request): ?array
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (! is_numeric($lat) || ! is_numeric($lng) || abs($lat) > 90 || abs($lng) > 180) {
            return null;
        }

        return ['lat' => round((float) $lat, 4), 'lng' => round((float) $lng, 4)];
    }

    private function state(Request $request): ?string
    {
        $state = strtoupper((string) $request->query('state'));

        return in_array($state, Venue::STATES) ? $state : null;
    }

    private function area(Request $request): ?string
    {
        return $request->string('area')->trim()->limit(60, '')->value() ?: null;
    }

    /** The values of a multi-choice query parameter that are among $allowed's keys. */
    private function pick(Request $request, string $key, array $allowed): array
    {
        return array_values(array_intersect((array) $request->query($key), array_keys($allowed)));
    }

    private function canPreview(Request $request): bool
    {
        return (bool) $request->user()?->canAny(['clubs.view', 'venues.view']);
    }

    private function showsDrafts(Request $request): bool
    {
        return $request->boolean('drafts') && $this->canPreview($request);
    }
}
