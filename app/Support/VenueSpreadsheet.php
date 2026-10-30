<?php

namespace App\Support;

use App\Http\Requests\Admin\VenueRequest;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;
use Throwable;

/**
 * Venues in and out of the "Venues" tab of Pickleball_Directory_Verified.xlsx.
 * Same headers both ways, so an export can be edited and imported again.
 */
class VenueSpreadsheet
{
    /** Spreadsheet header => form field. */
    public const COLUMNS = [
        'Venue ID' => 'code',
        'Venue name' => 'name',
        'Venue type' => 'venue_type',
        'Street address' => 'address',
        'Suburb' => 'suburb',
        'Postcode' => 'postcode',
        'Council (LGA)' => 'council',
        'City or region' => 'city',
        'State' => 'state',
        'Latitude' => 'latitude',
        'Longitude' => 'longitude',
        'Google place ID' => 'google_place_id',
        'Number of courts' => 'court_count',
        'Indoor/outdoor' => 'indoor_outdoor',
        'Surface' => 'surface',
        'Court lines' => 'court_lines',
        'Lights' => 'lights',
        'Covered' => 'facilities.covered',
        'Nets provided' => 'facilities.nets_provided',
        'Free to use' => 'facilities.free_to_use',
        'Price (AUD per court hour)' => 'price_per_hour',
        'Price notes' => 'price_notes',
        'Booking required' => 'facilities.booking_required',
        'Booking link' => 'booking_url',
        'Opening hours' => 'opening_hours',
        'Accessible' => 'facilities.accessible',
        'Toilets' => 'facilities.toilets',
        'Parking' => 'facilities.parking',
        'Website' => 'website',
        'Phone' => 'phone',
        'Clubs that play here' => 'club_codes',
        'Source type' => 'source_type',
        'Source URL' => 'source_url',
        'Date checked' => 'checked_at',
        'Checked by' => 'checked_by',
        'Record status' => 'status',
        'Notes' => 'notes',
    ];

    private const NUMERIC = ['latitude', 'longitude', 'court_count', 'price_per_hour'];

    private const LISTS = [
        'venue_type' => Venue::TYPES,
        'state' => Venue::STATES,
        'indoor_outdoor' => Venue::SETTINGS,
        'surface' => Venue::SURFACES,
        'court_lines' => Venue::COURT_LINES,
        'source_type' => Venue::SOURCE_TYPES,
        'status' => Venue::STATUSES,
    ];

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(string $path, string $extension): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $rows = $this->readRows($path, $extension);

        $headers = array_map(fn ($header) => trim((string) $header), $rows->current() ?? []);
        if (! in_array('Venue name', $headers)) {
            throw new RuntimeException('The first row needs the column headers, including “Venue name”. Export venues to get a file in the right layout.');
        }
        $rows->next();

        DB::transaction(function () use ($rows, $headers, &$result) {
            for ($line = 2; $rows->valid(); $rows->next(), $line++) {
                $data = $this->toFields(array_combine($headers, array_pad(array_slice($rows->current(), 0, count($headers)), count($headers), null)));

                // The directory pre-fills Venue IDs on empty rows, and has a sample row.
                if (blank($data['name'] ?? null) || ($data['code'] ?? null) === 'EXAMPLE') {
                    $result['skipped']++;

                    continue;
                }

                $venue = filled($data['code'] ?? null) ? Venue::withTrashed()->firstWhere('code', $data['code']) : null;
                $validator = Validator::make($data, VenueRequest::fields($venue), [], VenueRequest::labels());

                if ($validator->fails()) {
                    $label = $data['code'] ?? $data['name'];
                    $result['errors'][] = "Row {$line} ({$label}): ".implode(' ', $validator->errors()->all());

                    continue;
                }

                $venue ??= new Venue;
                $result[$venue->exists ? 'updated' : 'created']++;
                $venue->fillFromForm($validator->validated(), keepUnsent: true)->save();
                $venue->trashed() && $venue->restore();
            }
        });

        return $result;
    }

    /**
     * Write venues to the browser as .xlsx or .csv.
     *
     * @param  Builder<Venue>  $venues
     */
    public function export(Builder $venues, string $format): void
    {
        $writer = $format === 'csv' ? new CsvWriter : new XlsxWriter;
        $writer->openToFile('php://output');

        if ($writer instanceof XlsxWriter) {
            $writer->getCurrentSheet()->setName('Venues');
            $writer->getOptions()->setColumnWidthForRange(18, 1, count(self::COLUMNS));
            $writer->getOptions()->setColumnWidth(36, 2, 4);
        }

        $writer->addRow(Row::fromValues(array_keys(self::COLUMNS), (new Style)->setFontBold()->setBackgroundColor('FBE08C')));

        foreach ($venues->reorder()->orderBy('code')->orderBy('id')->lazy(500) as $venue) {
            $writer->addRow(Row::fromValues(array_map(fn (string $field) => $this->exportValue($venue, $field), self::COLUMNS)));
        }

        $writer->close();
    }

    /** @return \Generator<int, array<int, mixed>> */
    private function readRows(string $path, string $extension): \Generator
    {
        $reader = $extension === 'csv' ? new CsvReader : new XlsxReader;

        try {
            $reader->open($path);
        } catch (Throwable) {
            throw new RuntimeException('That file couldn’t be read. Upload an .xlsx or .csv file.');
        }

        try {
            $sheets = iterator_to_array($reader->getSheetIterator(), false);
            // The directory workbook has many tabs; use "Venues" when there is one.
            $sheet = collect($sheets)->first(fn ($sheet) => strcasecmp($sheet->getName(), 'Venues') === 0) ?? $sheets[0];

            foreach ($sheet->getRowIterator() as $row) {
                yield $row->toArray();
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * One spreadsheet row, cleaned up into the shape the venue form sends.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function toFields(array $row): array
    {
        $data = [];

        foreach (self::COLUMNS as $header => $field) {
            if (array_key_exists($header, $row)) {
                data_set($data, $field, $this->clean($field, $row[$header]));
            }
        }

        $data['status'] ??= 'To check';

        return $data;
    }

    private function clean(string $field, mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === '' || $value === null) {
            return null;
        }

        return match (true) {
            $field === 'lights', str_starts_with($field, 'facilities.') => match (Str::lower((string) $value)) {
                'yes', 'y', 'true', '1' => true,
                'no', 'n', 'false', '0' => false,
                'unknown' => null,
                default => $value, // left as-is so validation names the bad cell
            },
            $field === 'checked_at' => $this->date($value),
            // Excel turns 0800 into 800.
            $field === 'postcode' => is_numeric($value) ? str_pad((string) (int) $value, 4, '0', STR_PAD_LEFT) : $value,
            in_array($field, ['website', 'booking_url', 'source_url']) => preg_match('~^https?://~i', $value) ? $value : "https://{$value}",
            // "outdoor" -> "Outdoor"
            isset(self::LISTS[$field]) => collect(self::LISTS[$field])->first(fn ($option) => strcasecmp($option, $value) === 0) ?? $value,
            in_array($field, self::NUMERIC) => $value,
            // A phone number or ID typed into a number cell still belongs in a text column.
            default => is_float($value) && floor($value) === $value ? (string) (int) $value : (string) $value,
        };
    }

    private function date(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            $date = rescue(fn () => CarbonImmutable::createFromFormat("!{$format}", (string) $value), null, report: false);
            if ($date && $date->format($format) === (string) $value) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }

    private function exportValue(Venue $venue, string $field): mixed
    {
        $value = match (true) {
            str_starts_with($field, 'facilities.') => $venue->facility(Str::after($field, 'facilities.')),
            $field === 'club_codes' => implode(', ', $venue->clubCodes()),
            $field === 'checked_at' => $venue->checked_at?->format('d/m/Y'),
            $field === 'price_per_hour' => $venue->price_per_hour === null ? null : (float) $venue->price_per_hour,
            default => $venue->getAttribute($field),
        };

        return is_bool($value) ? ($value ? 'Yes' : 'No') : $value;
    }
}
