<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VenueRequest;
use App\Models\ActivityLog;
use App\Models\Venue;
use App\Support\VenueSpreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VenueController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:venues.view', only: ['index', 'show']),
            new Middleware('permission:venues.create', only: ['create', 'store']),
            new Middleware('permission:venues.update', only: ['edit', 'update']),
            new Middleware('permission:venues.delete', only: ['destroy']),
            new Middleware('permission:venues.export', only: ['export']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('admin.venues.index', [
            'venues' => $this->filtered($filters)->withCount('clubs')->orderBy('state')->orderBy('name')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'stats' => [
                'total' => Venue::count(),
                'states' => Venue::distinct()->count('state'),
                'published' => Venue::where('status', 'published')->count(),
                'ready' => Venue::where('record_status', 'checked')->where('status', 'draft')->count(),
                'confirmName' => Venue::where('name_needs_confirming', true)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.venues.form', ['venue' => new Venue(['ref' => Venue::nextRef()])]);
    }

    public function store(VenueRequest $request): RedirectResponse
    {
        $venue = new Venue($request->validated());
        $venue->save();

        ActivityLog::record('venue.created', "Added venue {$venue->name}", $venue, $venue->name);

        return redirect()->route('admin.venues.show', $venue)->with('status', 'Venue added.');
    }

    public function show(Venue $venue): View
    {
        $venue->load(['clubs' => fn ($query) => $query->orderByDesc('club_venue.is_primary')->orderBy('name')]);

        return view('admin.venues.show', ['venue' => $venue]);
    }

    public function edit(Venue $venue): View
    {
        return view('admin.venues.form', ['venue' => $venue]);
    }

    public function update(VenueRequest $request, Venue $venue): RedirectResponse
    {
        $venue->fill($request->validated());
        $changes = ActivityLog::diff($venue, array_keys(VenueRequest::fields()));
        $venue->save();

        if ($changes) {
            ActivityLog::record('venue.updated', "Updated venue {$venue->name}", $venue, $venue->name, $changes);
        }

        return redirect()->route('admin.venues.show', $venue)->with('status', $changes ? 'Venue updated.' : 'Nothing to change.');
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        $venue->delete();

        ActivityLog::record('venue.deleted', "Deleted venue {$venue->name}", $venue, $venue->name);

        return redirect()->route('admin.venues.index')->with('status', "Deleted {$venue->name}.");
    }

    public function export(Request $request, VenueSpreadsheet $spreadsheet): StreamedResponse
    {
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $filters = $this->filters($request);

        if ($request->boolean('template')) {
            // Just the header row, to fill in and import.
            $query = Venue::query()->whereKey([]);
            $filename = 'venues-template';
        } else {
            $query = $this->filtered($filters);
            $filename = 'venues-'.now()->format('Y-m-d');

            ActivityLog::record('venue.exported', 'Exported '.number_format($query->count()).' venues as '.strtoupper($format), changes: array_filter($filters)
                ? ['filters' => ['from' => null, 'to' => array_filter($filters)]]
                : null);
        }

        return response()->streamDownload(fn () => $spreadsheet->export($query, $format), "{$filename}.{$format}", ImportController::contentType($format));
    }

    /** @return array{search: ?string, state: ?string, setting: ?string, record_status: ?string, status: ?string} */
    private function filters(Request $request): array
    {
        $pick = fn (string $key, array $options) => array_key_exists((string) $request->query($key), $options) ? $request->query($key) : null;

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'state' => in_array($request->query('state'), Venue::STATES) ? $request->query('state') : null,
            'setting' => $pick('setting', Venue::SETTINGS),
            'record_status' => $pick('record_status', Venue::RECORD_STATUSES),
            'status' => $pick('status', Venue::STATUSES),
        ];
    }

    /** @return Builder<Venue> */
    private function filtered(array $filters): Builder
    {
        return Venue::query()
            ->search($filters['search'])
            ->when($filters['state'], fn (Builder $query, string $state) => $query->where('state', $state))
            ->when($filters['setting'], fn (Builder $query, string $setting) => $query->where('indoor_outdoor', $setting))
            ->when($filters['record_status'], fn (Builder $query, string $status) => $query->where('record_status', $status))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
