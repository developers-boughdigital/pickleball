<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\Venue;
use App\Support\ClubSpreadsheet;
use App\Support\ClubVenueSpreadsheet;
use App\Support\DirectorySpreadsheet;
use App\Support\VenueSpreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One place to load the directory import pack: venues.csv, then clubs.csv, then
 * club_venue.csv (the links use the venue and club refs).
 */
class ImportController extends Controller
{
    /** type => [label, file in the pack, permission, importer]. */
    public const TYPES = [
        'venues' => ['Venues', 'venues.csv', 'venues.import', VenueSpreadsheet::class],
        'clubs' => ['Clubs', 'clubs.csv', 'clubs.import', ClubSpreadsheet::class],
        'club_venue' => ['Club & venue links', 'club_venue.csv', 'clubs.import', ClubVenueSpreadsheet::class],
    ];

    public function index(Request $request): View
    {
        $types = $this->allowedTypes($request);
        abort_unless($types, 403);

        return view('admin.import', [
            'types' => $types,
            'selected' => old('type', in_array($request->query('type'), $types) ? $request->query('type') : $types[0]),
            'counts' => [
                'venues' => Venue::count(),
                'clubs' => Club::count(),
                'club_venue' => DB::table('club_venue')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->allowedTypes($request), 403);

        $request->validate([
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv'],
        ], ['file.extensions' => 'Upload an .xlsx or .csv file.']);

        $type = $request->input('type');
        [$label, , $permission, $importer] = self::TYPES[$type];
        abort_unless($request->user()->can($permission), 403);

        $file = $request->file('file');

        try {
            $result = app($importer)->import($file->getRealPath(), strtolower($file->getClientOriginalExtension()));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['file' => $e->getMessage()]);
        }

        $summary = $this->summary($type, $result);

        ActivityLog::record(Str::singular($type).'.imported', "Imported {$label} from {$file->getClientOriginalName()}: {$summary}", succeeded: ! $result['errors'], changes: [
            'file' => ['from' => null, 'to' => $file->getClientOriginalName()],
            'rows' => ['from' => null, 'to' => Arr::except($result, 'errors')],
        ]);

        return redirect()->route('admin.import', ['type' => $type])->with([
            'status' => "{$label} imported: {$summary}.",
            'import' => [...$result, 'type' => $type, 'file' => $file->getClientOriginalName()],
        ]);
    }

    /** Header-only file to fill in, or (for links) every current link. */
    public function template(Request $request, string $type): StreamedResponse
    {
        abort_unless(isset(self::TYPES[$type]) && $request->user()->can(self::TYPES[$type][2]), 404);
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';
        $importer = self::TYPES[$type][3];

        return response()->streamDownload(
            fn () => DirectorySpreadsheet::write($format, $importer::COLUMNS, fn () => null, $importer::NAME),
            str_replace('.csv', "-template.{$format}", self::TYPES[$type][1]),
            self::contentType($format),
        );
    }

    public function export(Request $request, ClubVenueSpreadsheet $spreadsheet): StreamedResponse
    {
        abort_unless($request->user()->can('clubs.export'), 403);
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';

        ActivityLog::record('club_venue.exported', 'Exported club & venue links as '.strtoupper($format));

        return response()->streamDownload(fn () => $spreadsheet->export($format), 'club-venue-'.now()->format('Y-m-d').".{$format}", self::contentType($format));
    }

    /** @return array{Content-Type: string} */
    public static function contentType(string $format): array
    {
        return ['Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    }

    /** @return list<string> */
    private function allowedTypes(Request $request): array
    {
        return array_keys(array_filter(self::TYPES, fn (array $type) => $request->user()->can($type[2])));
    }

    private function summary(string $type, array $result): string
    {
        $problems = $result['errors'] ? ', '.count($result['errors']).' with problems' : '';

        if ($type === 'club_venue') {
            return "{$result['created']} links added, {$result['updated']} changed, {$result['removed']} removed across {$result['clubs']} clubs{$problems}";
        }

        return "{$result['created']} added, {$result['updated']} updated{$problems}. {$result['drafts']} in draft; {$result['checked']} checked and ready to go live";
    }
}
