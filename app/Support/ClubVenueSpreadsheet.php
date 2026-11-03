<?php

namespace App\Support;

use App\Models\Club;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * club_venue.csv from the directory import pack: which venues each club plays at,
 * with is_primary = 1 for its main venue. Import venues and clubs first; the
 * links use their refs.
 *
 * The file is the full list for every club it mentions, so a re-import also
 * removes links that are no longer in it. Clubs not in the file keep theirs.
 */
class ClubVenueSpreadsheet
{
    public const NAME = 'Club venues';

    public const COLUMNS = ['club_ref', 'venue_ref', 'is_primary'];

    /**
     * @return array{created: int, updated: int, removed: int, clubs: int, skipped: int, errors: list<string>}
     */
    public function import(string $path, string $extension): array
    {
        $result = ['created' => 0, 'updated' => 0, 'removed' => 0, 'clubs' => 0, 'skipped' => 0, 'errors' => []];
        $rows = DirectorySpreadsheet::rows($path, $extension, self::NAME);
        $headers = DirectorySpreadsheet::headers($rows, ['club_ref', 'venue_ref']);

        $wanted = []; // club ref => [venue ref => is_primary]
        $lines = [];
        for ($line = 2; $rows->valid(); $rows->next(), $line++) {
            $row = DirectorySpreadsheet::combine($headers, $rows->current());
            $club = Str::upper(trim((string) $row['club_ref']));
            $venue = Str::upper(trim((string) $row['venue_ref']));

            if ($club === '' && $venue === '') {
                $result['skipped']++;

                continue;
            }
            if ($club === '' || $venue === '') {
                $result['errors'][] = "Row {$line}: needs both a club_ref and a venue_ref.";

                continue;
            }

            $primary = in_array(Str::lower(trim((string) ($row['is_primary'] ?? ''))), ['1', 'yes', 'y', 'true'], true);
            $wanted[$club][$venue] = ($wanted[$club][$venue] ?? false) || $primary;
            $lines[$club][$venue] = $line;
        }

        $clubs = Club::whereIn('ref', array_keys($wanted))->pluck('id', 'ref');
        $venues = Venue::whereIn('ref', collect($wanted)->flatMap(fn ($links) => array_keys($links))->unique()->all())->pluck('id', 'ref');

        DB::transaction(function () use ($wanted, $lines, $clubs, $venues, &$result) {
            foreach ($wanted as $clubRef => $links) {
                if (! $clubs->has($clubRef)) {
                    $result['errors'][] = 'Row '.implode(', ', $lines[$clubRef])." ({$clubRef}): there is no club {$clubRef}. Import clubs first.";

                    continue;
                }

                $sync = [];
                foreach ($links as $venueRef => $primary) {
                    if ($venues->has($venueRef)) {
                        $sync[$venues[$venueRef]] = ['is_primary' => $primary];
                    } else {
                        $result['errors'][] = "Row {$lines[$clubRef][$venueRef]} ({$clubRef}): there is no venue {$venueRef}. Import venues first.";
                    }
                }

                $club = Club::find($clubs[$clubRef]);
                $existing = $club->venues()->pluck('is_primary', 'venues.id')->map(fn ($primary) => (bool) $primary);

                $result['created'] += count(array_diff_key($sync, $existing->all()));
                $result['removed'] += $existing->diffKeys($sync)->count();
                $result['updated'] += collect($sync)->filter(fn ($pivot, $id) => $existing->has($id) && $existing[$id] !== $pivot['is_primary'])->count();
                $result['clubs']++;

                $club->venues()->sync($sync);
            }
        });

        return $result;
    }

    /** Every link, by club then venue ref, in the same columns as the file. */
    public function export(string $format): void
    {
        DirectorySpreadsheet::write($format, self::COLUMNS, function (callable $add) {
            DB::table('club_venue')
                ->join('clubs', 'clubs.id', '=', 'club_venue.club_id')
                ->join('venues', 'venues.id', '=', 'club_venue.venue_id')
                ->whereNull('clubs.deleted_at')
                ->whereNull('venues.deleted_at')
                ->orderBy('clubs.ref')
                ->orderByDesc('club_venue.is_primary')
                ->orderBy('venues.ref')
                ->select('clubs.ref as club_ref', 'venues.ref as venue_ref', 'club_venue.is_primary')
                ->cursor()
                ->each(fn ($link) => $add([$link->club_ref, $link->venue_ref, (int) $link->is_primary]));
        }, self::NAME);
    }
}
