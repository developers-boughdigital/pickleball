<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Venue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public directory: find a club to join or a venue to play at.
 *
 * Only published listings are shown (files/IMPORT_GUIDE.md, rule 3). Admins can
 * add ?drafts=1 to preview everything before it goes live.
 */
class ExploreController extends Controller
{
    private const PER_PAGE = 24;

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'venues' ? 'venues' : 'clubs';
        $drafts = $this->showsDrafts($request);
        $near = $this->near($request);

        $filters = [
            'q' => $request->string('q')->trim()->limit(100, '')->value() ?: null,
            'state' => in_array($request->query('state'), Venue::STATES) ? $request->query('state') : null,
            'level' => array_key_exists((string) $request->query('level'), Club::LEVELS) ? $request->query('level') : null,
            'beginners' => $request->boolean('beginners'),
            'coaching' => $request->boolean('coaching'),
            'setting' => array_key_exists((string) $request->query('setting'), Venue::SETTINGS) ? $request->query('setting') : null,
            'lights' => $request->boolean('lights'),
        ];

        $results = $tab === 'clubs' ? $this->clubs($filters, $near, $drafts) : $this->venues($filters, $near, $drafts);

        return view('explore.index', [
            'tab' => $tab,
            'filters' => $filters,
            'near' => $near,
            'drafts' => $drafts,
            'canPreview' => $this->canPreview($request),
            'results' => $results->withQueryString(),
            'counts' => [
                'clubs' => Club::query()->visible($drafts)->count(),
                'venues' => Venue::query()->visible($drafts)->count(),
            ],
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

    private function clubs(array $filters, ?array $near, bool $drafts): LengthAwarePaginator
    {
        $query = Club::query()
            ->visible($drafts)
            ->search($filters['q'])
            ->withCount(['venues' => fn (Builder $query) => $query->visible($drafts)])
            ->when($filters['state'], fn (Builder $query, string $state) => $query->where('state', $state))
            ->when($filters['level'], fn (Builder $query, string $level) => $query->whereJsonContains('levels', $level))
            ->when($filters['beginners'], fn (Builder $query) => $query->where('beginners_welcome', true))
            ->when($filters['coaching'], fn (Builder $query) => $query->where('coaching_available', true));

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

    private function venues(array $filters, ?array $near, bool $drafts): LengthAwarePaginator
    {
        $query = Venue::query()
            ->visible($drafts)
            ->search($filters['q'])
            ->withCount(['clubs' => fn (Builder $query) => $query->visible($drafts)])
            ->when($filters['state'], fn (Builder $query, string $state) => $query->where('state', $state))
            ->when($filters['setting'], fn (Builder $query, string $setting) => $query->whereIn('indoor_outdoor', $setting === 'both' ? ['both'] : [$setting, 'both']))
            ->when($filters['lights'], fn (Builder $query) => $query->where('lights', true));

        if ($near) {
            $query->selectRaw('coalesce('.$this->distanceSql($near).', '.Venue::UNKNOWN_DISTANCE.') as distance')->orderBy('distance');
        }

        return $query->orderBy('name')->paginate(self::PER_PAGE);
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

    private function canPreview(Request $request): bool
    {
        return (bool) $request->user()?->canAny(['clubs.view', 'venues.view']);
    }

    private function showsDrafts(Request $request): bool
    {
        return $request->boolean('drafts') && $this->canPreview($request);
    }
}
