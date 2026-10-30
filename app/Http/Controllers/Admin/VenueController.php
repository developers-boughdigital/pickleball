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
use Illuminate\Support\Arr;
use Illuminate\View\View;
use RuntimeException;
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
            new Middleware('permission:venues.import', only: ['import']),
            new Middleware('permission:venues.export', only: ['export']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('admin.venues.index', [
            'venues' => $this->filtered($filters)->orderBy('state')->orderBy('name')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'stats' => [
                'total' => Venue::count(),
                'open' => Venue::where('status', 'Open')->count(),
                'closed' => Venue::where('status', 'Closed')->count(),
                'courts' => (int) Venue::sum('court_count'),
                'states' => Venue::distinct()->count('state'),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.venues.form', ['venue' => new Venue(['status' => 'Open','code' => Venue::nextCode()])]);
    }

    public function store(VenueRequest $request): RedirectResponse
    {
        $venue = (new Venue)->fillFromForm($request->validated());
        $venue->save();

        ActivityLog::record('venue.created', "Added venue {$venue->name}", $venue, $venue->name);

        return redirect()->route('admin.venues.show', $venue)->with('status', 'Venue added.');
    }

    public function show(Venue $venue): View
    {
        return view('admin.venues.show', ['venue' => $venue]);
    }

    public function edit(Venue $venue): View
    {
        return view('admin.venues.form', ['venue' => $venue]);
    }

    public function update(VenueRequest $request, Venue $venue): RedirectResponse
    {
        $venue->fillFromForm($request->validated());
        $changes = ActivityLog::diff($venue, [...array_keys(VenueRequest::fields()), 'attributes']);
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

    public function import(Request $request, VenueSpreadsheet $spreadsheet): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv'],
        ], ['file.extensions' => 'Upload an .xlsx or .csv file.']);

        $file = $request->file('file');

        try {
            $result = $spreadsheet->import($file->getRealPath(), strtolower($file->getClientOriginalExtension()));
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $summary = "{$result['created']} added, {$result['updated']} updated";
        if ($result['errors']) {
            $summary .= ', '.count($result['errors']).' with problems';
        }

        ActivityLog::record('venue.imported', "Imported venues from {$file->getClientOriginalName()}: {$summary}", succeeded: ! $result['errors'], changes: [
            'file' => ['from' => null, 'to' => $file->getClientOriginalName()],
            'rows' => ['from' => null, 'to' => Arr::except($result, 'errors')],
        ]);

        return redirect()->route('admin.venues.index')->with([
            'status' => "Import finished: {$summary}.",
            'import' => $result,
        ]);
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

        return response()->streamDownload(
            fn () => $spreadsheet->export($query, $format),
            "{$filename}.{$format}",
            ['Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /** @return array{search: ?string, state: ?string, status: ?string, setting: ?string} */
    private function filters(Request $request): array
    {
        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'state' => in_array($request->query('state'), Venue::STATES) ? $request->query('state') : null,
            'status' => in_array($request->query('status'), Venue::STATUSES) ? $request->query('status') : null,
            'setting' => in_array($request->query('setting'), Venue::SETTINGS) ? $request->query('setting') : null,
        ];
    }

    /** @return Builder<Venue> */
    private function filtered(array $filters): Builder
    {
        return Venue::query()
            ->search($filters['search'])
            ->when($filters['state'], fn (Builder $query, string $state) => $query->where('state', $state))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['setting'], fn (Builder $query, string $setting) => $query->where('indoor_outdoor', $setting));
    }
}
