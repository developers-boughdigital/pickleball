<?php

namespace App\Support;

use App\Models\Club;
use App\Models\Locality;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Loads the suburb lookup and fills city and council on the directory. */
class Geography
{
    public const DATA_FILE = 'database/data/localities.csv';

    /**
     * Replaces the localities table from a CSV with the columns
     * postcode, suburb, state, council, sa4, sa3, latitude, longitude.
     */
    public static function importLocalities(?string $path = null): int
    {
        $path ??= base_path(self::DATA_FILE);
        $handle = @fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException("Can’t open {$path}.");
        }

        $headers = fgetcsv($handle, escape: '');
        $missing = array_diff(['postcode', 'suburb', 'state', 'council', 'sa4', 'sa3', 'latitude', 'longitude'], (array) $headers);
        if ($missing) {
            throw new RuntimeException('The file is missing the columns: '.implode(', ', $missing).'.');
        }

        $now = now();
        $rows = [];
        while (($cells = fgetcsv($handle, escape: '')) !== false) {
            if (count($cells) !== count($headers)) {
                continue;
            }
            $row = array_combine($headers, $cells);
            if (! in_array($row['state'], Venue::STATES)) {
                continue;
            }

            $key = $row['postcode'].'|'.mb_strtoupper($row['suburb']);
            $rows[$key] = [
                'postcode' => str_pad($row['postcode'], 4, '0', STR_PAD_LEFT),
                'suburb' => Locality::suburbName($row['suburb']),
                'state' => $row['state'],
                'council' => Locality::councilFor($row['state'], $row['council'], $row['sa3']),
                'city' => Locality::cityFor($row['state'], $row['sa4']),
                'latitude' => is_numeric($row['latitude']) && (float) $row['latitude'] ? (float) $row['latitude'] : null,
                'longitude' => is_numeric($row['longitude']) && (float) $row['longitude'] ? (float) $row['longitude'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        DB::transaction(function () use ($rows) {
            Locality::query()->delete();
            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                Locality::insert($chunk);
            }
        });

        return count($rows);
    }

    /**
     * Fills a blank city and council on every venue and club that has a postcode.
     *
     * @return array{venues: int, clubs: int}
     */
    public static function fillDirectory(): array
    {
        $filled = ['venues' => 0, 'clubs' => 0];

        foreach (['venues' => Venue::class, 'clubs' => Club::class] as $key => $model) {
            $model::query()
                ->whereNotNull('postcode')
                ->where(fn ($query) => $query->whereNull('city')->orWhereNull('council'))
                ->each(function ($listing) use (&$filled, $key) {
                    if (Locality::fillListing($listing)) {
                        $listing->saveQuietly();
                        $filled[$key]++;
                    }
                });
        }

        return $filled;
    }

    /**
     * How many listings have each of the four location levels.
     *
     * @return array<string, array{total: int, state: int, city: int, council: int, suburb: int, complete: int}>
     */
    public static function coverage(): array
    {
        return collect(['venues' => Venue::class, 'clubs' => Club::class])->map(fn (string $model) => (array) $model::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(state) as state, count(city) as city, count(council) as council, count(suburb) as suburb')
            ->selectRaw('sum(case when state is not null and city is not null and council is not null and suburb is not null then 1 else 0 end) as complete')
            ->toBase()->first())
            ->map(fn (array $row) => array_map('intval', $row))
            ->all();
    }
}
