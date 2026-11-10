<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ChangesListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClubRequest;
use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\Venue;
use App\Support\ClubSpreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClubController extends Controller implements HasMiddleware
{
    use ChangesListingStatus;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:clubs.view', only: ['index', 'show']),
            new Middleware('permission:clubs.create', only: ['create', 'store']),
            new Middleware('permission:clubs.update', only: ['edit', 'update']),
            new Middleware('permission:clubs.delete', only: ['destroy']),
            new Middleware('permission:clubs.export', only: ['export']),
            new Middleware('permission:clubs.publish', only: ['status']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('admin.clubs.index', [
            'clubs' => $this->filtered($filters)->withCount('venues')->orderBy('state')->orderBy('name')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'stats' => [
                'total' => Club::count(),
                'states' => Club::distinct()->count('state'),
                'published' => Club::where('status', 'published')->count(),
                'hidden' => Club::where('status', 'hidden')->count(),
                'ready' => Club::where('record_status', 'checked')->where('status', 'draft')->count(),
                'noVenue' => Club::doesntHave('venues')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.clubs.form', ['club' => new Club(['ref' => Club::nextRef()])]);
    }

    public function store(ClubRequest $request): RedirectResponse
    {
        $club = Club::create($request->clubData());
        $club->venues()->sync($this->venueIds($request));

        ActivityLog::record('club.created', "Added club {$club->name}", $club, $club->name);

        return redirect()->route('admin.clubs.show', $club)->with('status', 'Club added.');
    }

    public function show(Club $club): View
    {
        $club->load(['venues' => fn ($query) => $query->orderByDesc('club_venue.is_primary')->orderBy('name')]);

        return view('admin.clubs.show', ['club' => $club]);
    }

    public function edit(Club $club): View
    {
        return view('admin.clubs.form', ['club' => $club->load('venues')]);
    }

    public function update(ClubRequest $request, Club $club): RedirectResponse
    {
        $club->fill($request->clubData());
        $changes = ActivityLog::diff($club, array_keys(ClubRequest::fields()));
        $club->save();

        $before = $this->venueSummary($club);
        $club->venues()->sync($this->venueIds($request));
        if ($before !== ($after = $this->venueSummary($club))) {
            $changes['venues'] = ['from' => $before, 'to' => $after];
        }

        if ($changes) {
            ActivityLog::record('club.updated', "Updated club {$club->name}", $club, $club->name, $changes);
        }

        return redirect()->route('admin.clubs.show', $club)->with('status', $changes ? 'Club updated.' : 'Nothing to change.');
    }

    public function destroy(Club $club): RedirectResponse
    {
        $club->delete();

        ActivityLog::record('club.deleted', "Deleted club {$club->name}", $club, $club->name);

        return redirect()->route('admin.clubs.index')->with('status', "Deleted {$club->name}.");
    }

    public function export(Request $request, ClubSpreadsheet $spreadsheet): StreamedResponse
    {
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $filters = $this->filters($request);

        if ($request->boolean('template')) {
            $query = Club::query()->whereKey([]);
            $filename = 'clubs-template';
        } else {
            $query = $this->filtered($filters);
            $filename = 'clubs-'.now()->format('Y-m-d');

            ActivityLog::record('club.exported', 'Exported '.number_format($query->count()).' clubs as '.strtoupper($format), changes: array_filter($filters)
                ? ['filters' => ['from' => null, 'to' => array_filter($filters)]]
                : null);
        }

        return response()->streamDownload(fn () => $spreadsheet->export($query, $format), "{$filename}.{$format}", ImportController::contentType($format));
    }

    /** venue id => pivot, from the refs on the form. */
    private function venueIds(ClubRequest $request): array
    {
        $refs = $request->venueRefs();
        $ids = Venue::whereIn('ref', array_keys($refs))->pluck('id', 'ref');

        return collect($refs)->mapWithKeys(fn (bool $primary, string $ref) => [$ids[$ref] => ['is_primary' => $primary]])->all();
    }

    /** "V0001 (main), V0002" for the activity log. */
    private function venueSummary(Club $club): string
    {
        return $club->venues()->orderByDesc('club_venue.is_primary')->orderBy('ref')->get()
            ->map(fn (Venue $venue) => $venue->ref.($venue->pivot->is_primary ? ' (main)' : ''))
            ->implode(', ');
    }

    /** @return array{search: ?string, state: ?string, record_status: ?string, status: ?string} */
    /** Publish, hide or return to draft: one club, the ticked rows, or all matching the filters. */
    public function status(Request $request): RedirectResponse
    {
        return $this->changeStatus($request, $this->filtered($this->filters($request)), 'club');
    }

    private function filters(Request $request): array
    {
        $pick = fn (string $key, array $options) => array_key_exists((string) $request->query($key), $options) ? $request->query($key) : null;

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'state' => in_array($request->query('state'), Club::STATES) ? $request->query('state') : null,
            'record_status' => $pick('record_status', Club::RECORD_STATUSES),
            'status' => $pick('status', Club::STATUSES),
        ];
    }

    /** @return Builder<Club> */
    private function filtered(array $filters): Builder
    {
        return Club::query()
            ->search($filters['search'])
            ->when($filters['state'], fn (Builder $query, string $state) => $query->where('state', $state))
            ->when($filters['record_status'], fn (Builder $query, string $status) => $query->where('record_status', $status))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
