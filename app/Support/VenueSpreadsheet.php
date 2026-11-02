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
 * Venues in and out of venues.csv from the directory import pack (files/IMPORT_GUIDE.md).
 * The headers are the table's column names, so an export can be edited and imported again.
 */
class VenueSpreadsheet
{
    /** venues.csv columns in order, then status, which only the export adds. */
    public const COLUMNS = [
        'ref', 'slug', 'name', 'name_needs_confirming', 'venue_type',
        'street_address', 'suburb', 'postcode', 'council', 'city', 'state', 'latitude', 'longitude', 'google_place_id',
        'court_count', 'indoor_outdoor', 'surface', 'court_lines', 'nets', 'lights', 'covered', 'nets_provided', 'free_to_use',
        'court_hire_cents', 'price_notes', 'booking_required', 'booking_url', 'opening_hours_text',
        'accessible', 'toilets', 'parking', 'changing_rooms', 'cafe_kiosk', 'paddle_hire', 'website', 'phone',
        'source_type', 'source_url', 'record_status', 'checked_at', 'checked_by', 'profile_tier', 'claim_status', 'enriched_at', 'notes',
        'status',
    ];

    private const BOOLEANS = [...Venue::FACILITIES, 'name_needs_confirming' => ''];

    private const NUMERIC = ['latitude', 'longitude', 'court_count', 'court_hire_cents', 'profile_tier'];

    private const DATES = ['checked_at', 'enriched_at'];

    /**
     * Insert new refs and update existing ones. Empty cells mean unknown and are
     * saved as NULL; columns missing from the file are left alone.
     *
     * @return array{created: int, updated: int, skipped: int, drafts: int, checked: int, errors: list<string>}
     */
    public function import(string $path, string $extension): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'drafts' => 0, 'checked' => 0, 'errors' => []];
        $rows = $this->readRows($path, $extension);

        $headers = array_map(fn ($header) => Str::lower(trim((string) $header)), $rows->current() ?? []);
        if (! in_array('name', $headers)) {
            throw new RuntimeException('The first row needs the column headers, including “name”. Export venues to get a file in the right layout.');
        }
        $rows->next();

        DB::transaction(function () use ($rows, $headers, &$result) {
            for ($line = 2; $rows->valid(); $rows->next(), $line++) {
                $data = $this->toFields(array_combine($headers, array_pad(array_slice($rows->current(), 0, count($headers)), count($headers), null)));

                if (blank($data['name'] ?? null)) {
                    $result['skipped']++;

                    continue;
                }

                $venue = filled($data['ref'] ?? null) ? Venue::withTrashed()->firstWhere('ref', $data['ref']) : null;

                if ($venue) {
                    unset($data['slug']); // set once, never changed by a re-import
                }
                // These always have a value: an empty cell keeps what's there, or the default.
                $data['record_status'] ??= $venue->record_status ?? 'to_check';
                $data['claim_status'] ??= $venue->claim_status ?? 'not_contacted';
                $data['profile_tier'] ??= $venue->profile_tier ?? 1;
                $data['status'] ??= $venue->status ?? 'draft';
                // A venue that is no longer checked goes back to draft rather than failing the row.
                if ($data['status'] === 'published' && $data['record_status'] !== 'checked') {
                    $data['status'] = 'draft';
                }

                $validator = Validator::make($data, VenueRequest::fields($venue), VenueRequest::messagesFor(), VenueRequest::labels());

                if ($validator->fails()) {
                    $label = $data['ref'] ?? $data['name'];
                    $result['errors'][] = "Row {$line} ({$label}): ".implode(' ', $validator->errors()->all());

                    continue;
                }

                $venue ??= new Venue;
                $result[$venue->exists ? 'updated' : 'created']++;
                $venue->fill($validator->validated())->save();
                $venue->trashed() && $venue->restore();

                $venue->status === 'draft' && $result['drafts']++;
                $venue->record_status === 'checked' && $result['checked']++;
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
            $writer->getOptions()->setColumnWidthForRange(16, 1, count(self::COLUMNS));
            $writer->getOptions()->setColumnWidth(36, 3);
        }

        $writer->addRow(Row::fromValues(self::COLUMNS, (new Style)->setFontBold()->setBackgroundColor('FBE08C')));

        foreach ($venues->reorder()->orderBy('ref')->orderBy('id')->lazy(500) as $venue) {
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
            // A workbook with several tabs: use "Venues" when there is one.
            $sheet = collect($sheets)->first(fn ($sheet) => strcasecmp($sheet->getName(), 'Venues') === 0) ?? $sheets[0];

            foreach ($sheet->getRowIterator() as $row) {
                yield $row->toArray();
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * One row keyed by header, cleaned up into the shape the venue form sends.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function toFields(array $row): array
    {
        $data = [];

        foreach (self::COLUMNS as $field) {
            if (array_key_exists($field, $row)) {
                $data[$field] = $this->clean($field, $row[$field]);
            }
        }

        return $data;
    }

    private function clean(string $field, mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === '' || $value === null) {
            return $field === 'name_needs_confirming' ? false : null;
        }

        return match (true) {
            isset(self::BOOLEANS[$field]) => match (Str::lower((string) $value)) {
                '1', 'yes', 'y', 'true' => true,
                '0', 'no', 'n', 'false' => false,
                'unknown' => null,
                default => $value, // left as-is so validation names the bad cell
            },
            in_array($field, self::DATES) => $this->date($value),
            // Excel turns 0800 into 800.
            $field === 'postcode' => is_numeric($value) ? str_pad((string) (int) $value, 4, '0', STR_PAD_LEFT) : $value,
            in_array($field, ['website', 'booking_url']) => preg_match('~^https?://~i', $value) ? $value : "https://{$value}",
            $field === 'state' => Str::upper($value),
            isset(Venue::LISTS[$field]) => $this->code(Venue::LISTS[$field], (string) $value),
            in_array($field, self::NUMERIC) => $value,
            // A phone number typed into a number cell still belongs in a text column.
            default => is_float($value) && floor($value) === $value ? (string) (int) $value : (string) $value,
        };
    }

    /** Accepts the code ("leisure_centre") or the label ("Leisure centre"). */
    private function code(array $options, string $value): string
    {
        $key = Str::snake(Str::lower(preg_replace('/[^A-Za-z0-9]+/', ' ', $value)));

        foreach ($options as $code => $label) {
            if ($key === $code || $key === Str::snake(Str::lower(preg_replace('/[^A-Za-z0-9]+/', ' ', $label)))) {
                return $code;
            }
        }

        return $value;
    }

    private function date(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
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
            in_array($field, self::DATES) => $venue->getAttribute($field)?->format('Y-m-d'),
            default => $venue->getAttribute($field),
        };

        // Same formats as the pack: 1/0 for booleans, empty for unknown.
        return is_bool($value) ? (int) $value : $value;
    }
}
