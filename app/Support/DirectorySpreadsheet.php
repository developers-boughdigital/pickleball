<?php

namespace App\Support;

use App\Http\Requests\Admin\DirectoryRequest;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
 * A listings file from the directory import pack (venues.csv, clubs.csv), in and out.
 * The headers are the table's column names, so an export can be edited and imported
 * again. Rules from files/IMPORT_GUIDE.md: match on ref, set a slug once, store an
 * empty cell as NULL (unknown), and add new listings as drafts.
 */
abstract class DirectorySpreadsheet
{
    /** @var class-string<Model> */
    protected const MODEL = Model::class;

    /** @var class-string<DirectoryRequest> */
    protected const REQUEST = DirectoryRequest::class;

    /** Sheet name in an .xlsx, and the export file name. */
    public const NAME = '';

    /** The pack's columns in order, then status, which only the export adds. */
    public const COLUMNS = [];

    protected const BOOLEANS = [];

    protected const NUMERIC = [];

    protected const URLS = [];

    /** Columns holding a list, written "new|beginner". */
    protected const PIPE_LISTS = [];

    /** Columns holding a code from the model's LISTS. */
    protected const CODED = [];

    protected const DATES = ['checked_at', 'enriched_at'];

    /**
     * Insert new refs and update existing ones. Columns missing from the file are
     * left alone.
     *
     * @return array{created: int, updated: int, skipped: int, drafts: int, checked: int, errors: list<string>}
     */
    public function import(string $path, string $extension): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'drafts' => 0, 'checked' => 0, 'errors' => []];
        $rows = static::rows($path, $extension, static::NAME);
        $headers = static::headers($rows, ['name']);

        DB::transaction(function () use ($rows, $headers, &$result) {
            for ($line = 2; $rows->valid(); $rows->next(), $line++) {
                $data = $this->toFields(static::combine($headers, $rows->current()));

                if (blank($data['name'] ?? null)) {
                    $result['skipped']++;

                    continue;
                }

                $model = static::MODEL;
                $listing = filled($data['ref'] ?? null) ? $model::withTrashed()->firstWhere('ref', $data['ref']) : null;

                if ($listing) {
                    unset($data['slug']); // set once, never changed by a re-import
                }
                // These always have a value: an empty cell keeps what's there, or the default.
                $data['record_status'] ??= $listing->record_status ?? 'to_check';
                $data['claim_status'] ??= $listing->claim_status ?? 'not_contacted';
                $data['profile_tier'] ??= $listing->profile_tier ?? 1;
                $data['status'] ??= $listing->status ?? 'draft';
                // A listing that is no longer checked goes back to draft rather than failing the row.
                if ($data['status'] === 'published' && $data['record_status'] !== 'checked') {
                    $data['status'] = 'draft';
                }

                $request = static::REQUEST;
                $validator = Validator::make($data, $request::fields($listing), $request::messagesFor(), $request::labels());

                if ($validator->fails()) {
                    $label = $data['ref'] ?? $data['name'];
                    $result['errors'][] = "Row {$line} ({$label}): ".implode(' ', $validator->errors()->all());

                    continue;
                }

                $listing ??= new $model;
                $isNew = ! $listing->exists;
                $listing->fill($validator->validated());

                // Same data as already stored: leave the row alone so "updated" only means a real change.
                if (! $isNew && ! $listing->isDirty() && ! $listing->trashed()) {
                    $listing->status === 'draft' && $result['drafts']++;
                    $listing->record_status === 'checked' && $result['checked']++;

                    continue;
                }

                $result[$isNew ? 'created' : 'updated']++;
                $listing->save();
                $listing->trashed() && $listing->restore();

                $listing->status === 'draft' && $result['drafts']++;
                $listing->record_status === 'checked' && $result['checked']++;
            }
        });

        return $result;
    }

    /**
     * Write listings to the browser as .xlsx or .csv.
     */
    public function export(Builder $listings, string $format): void
    {
        static::write($format, static::COLUMNS, function (callable $add) use ($listings) {
            foreach ($listings->reorder()->orderBy('ref')->orderBy('id')->lazy(500) as $listing) {
                $add(array_map(fn (string $field) => $this->exportValue($listing, $field), static::COLUMNS));
            }
        });
    }

    /**
     * Stream a sheet: a bold header row, then whatever $rows adds.
     *
     * @param  callable(callable(array): void): void  $rows
     */
    public static function write(string $format, array $headers, callable $rows, ?string $sheet = null): void
    {
        $writer = $format === 'csv' ? new CsvWriter : new XlsxWriter;
        $writer->openToFile('php://output');

        if ($writer instanceof XlsxWriter) {
            $writer->getCurrentSheet()->setName($sheet ?? (static::NAME ?: 'Sheet1'));
            $writer->getOptions()->setColumnWidthForRange(16, 1, count($headers));
        }

        $writer->addRow(Row::fromValues($headers, (new Style)->setFontBold()->setBackgroundColor('FBE08C')));
        $rows(fn (array $values) => $writer->addRow(Row::fromValues($values)));

        $writer->close();
    }

    /**
     * Rows of an .xlsx (the tab called $sheetName when there is one) or .csv.
     *
     * @return \Generator<int, array<int, mixed>>
     */
    public static function rows(string $path, string $extension, string $sheetName = ''): \Generator
    {
        $reader = $extension === 'csv' ? new CsvReader : new XlsxReader;

        try {
            $reader->open($path);
        } catch (Throwable) {
            throw new RuntimeException('That file couldn’t be read. Upload an .xlsx or .csv file.');
        }

        try {
            $sheets = iterator_to_array($reader->getSheetIterator(), false);
            $sheet = collect($sheets)->first(fn ($sheet) => strcasecmp($sheet->getName(), $sheetName) === 0) ?? $sheets[0];

            foreach ($sheet->getRowIterator() as $row) {
                yield $row->toArray();
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Read the header row (lower case) and move on to the data.
     *
     * @param  list<string>  $required
     * @return list<string>
     */
    public static function headers(\Generator $rows, array $required): array
    {
        $headers = array_map(fn ($header) => Str::lower(trim((string) $header)), $rows->current() ?? []);

        if ($missing = array_diff($required, $headers)) {
            throw new RuntimeException('The first row needs the column headers, including “'.implode('”, “', $missing).'”. Check you picked the right file for this import.');
        }

        $rows->next();

        return $headers;
    }

    /** @return array<string, mixed> header => cell */
    public static function combine(array $headers, array $cells): array
    {
        return array_combine($headers, array_pad(array_slice($cells, 0, count($headers)), count($headers), null));
    }

    /**
     * One row cleaned up into the shape the form sends.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function toFields(array $row): array
    {
        $data = [];

        foreach (static::COLUMNS as $field) {
            if (array_key_exists($field, $row)) {
                $data[$field] = $this->clean($field, $row[$field]);
            }
        }

        return $data;
    }

    protected function clean(string $field, mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === '' || $value === null) {
            return null;
        }

        $model = static::MODEL;

        return match (true) {
            in_array($field, static::BOOLEANS) => match (Str::lower((string) $value)) {
                '1', 'yes', 'y', 'true' => true,
                '0', 'no', 'n', 'false' => false,
                'unknown' => null,
                default => $value, // left as-is so validation names the bad cell
            },
            in_array($field, static::DATES) => $this->date($value),
            in_array($field, static::PIPE_LISTS) => Str::of((string) $value)->explode('|')->map(fn ($item) => Str::lower(trim($item)))->filter()->values()->all() ?: null,
            // Excel turns 0800 into 800.
            $field === 'postcode' => is_numeric($value) ? str_pad((string) (int) $value, 4, '0', STR_PAD_LEFT) : $value,
            in_array($field, static::URLS) => preg_match('~^https?://~i', $value) ? $value : "https://{$value}",
            $field === 'state' => Str::upper($value),
            // "club@gmail.com." — a full stop copied from the end of a sentence.
            $field === 'email' => Str::lower(rtrim($value, '.,; ')),
            in_array($field, static::CODED) => $this->code($model::LISTS[$field], (string) $value),
            in_array($field, static::NUMERIC) => $value,
            // A phone number typed into a number cell still belongs in a text column.
            default => is_float($value) && floor($value) === $value ? (string) (int) $value : (string) $value,
        };
    }

    /** Accepts the code ("leisure_centre") or the label ("Leisure centre"). */
    private function code(array $options, string $value): string
    {
        $snake = fn (string $text) => Str::snake(Str::lower(preg_replace('/[^A-Za-z0-9]+/', ' ', $text)));

        foreach ($options as $code => $label) {
            if ($snake($value) === $code || $snake($value) === $snake($label)) {
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

    private function exportValue(Model $listing, string $field): mixed
    {
        $value = $listing->getAttribute($field);

        // Same formats as the pack: 1/0 for booleans (empty for unknown), Y-m-d dates, a|b lists.
        return match (true) {
            is_bool($value) => (int) $value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_array($value) => implode('|', $value),
            default => $value,
        };
    }
}
