<?php

namespace App\Support;

use App\Models\Club;
use App\Models\Locality;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A place in the location hierarchy: Australia › state › city › council › suburb.
 * Each level is a real page at /play/{state}/{city}/{council}/{suburb}/
 * (brief: "Information architecture and taxonomy").
 */
final class Area
{
    public const LEVELS = ['state', 'city', 'council', 'suburb'];

    private function __construct(
        public readonly ?string $state = null,
        public readonly ?string $city = null,
        public readonly ?string $council = null,
        public readonly ?string $suburb = null,
    ) {}

    public static function australia(): self
    {
        return new self;
    }

    public static function make(?string $state, ?string $city = null, ?string $council = null, ?string $suburb = null): self
    {
        return new self($state, $state ? $city : null, $state && $city ? $council : null, $state && $city && $council ? $suburb : null);
    }

    /**
     * The area for URL segments, matching each one against the place names the
     * directory and the suburb lookup know about. Null when a segment matches nothing.
     */
    public static function fromSlugs(string $state, ?string $city = null, ?string $council = null, ?string $suburb = null): ?self
    {
        $state = strtoupper($state);
        if (! in_array($state, Venue::STATES)) {
            return null;
        }

        $area = new self($state);
        foreach (['city' => $city, 'council' => $council, 'suburb' => $suburb] as $level => $slug) {
            if ($slug === null) {
                break;
            }
            $name = $area->childNames($level)->first(fn (string $name) => Str::slug($name) === $slug);
            if ($name === null) {
                return null;
            }
            $area = $area->with($level, $name);
        }

        return $area;
    }

    /** The deepest level set: australia, state, city, council or suburb. */
    public function level(): string
    {
        return collect(array_reverse(self::LEVELS))->first(fn (string $level) => $this->{$level} !== null) ?? 'australia';
    }

    /** The level below this one, or null for a suburb. */
    public function childLevel(): ?string
    {
        return [
            'australia' => 'state',
            'state' => 'city',
            'city' => 'council',
            'council' => 'suburb',
        ][$this->level()] ?? null;
    }

    /** "Australia", "Victoria", "Melbourne", "Banyule", "Greensborough". */
    public function name(): string
    {
        return match ($this->level()) {
            'australia' => 'Australia',
            'state' => Venue::STATE_NAMES[$this->state],
            default => $this->{$this->level()},
        };
    }

    public function with(string $level, string $name): self
    {
        $values = $this->values();
        $values[$level] = $name;
        // Changing a level clears the levels below it.
        foreach (array_slice(self::LEVELS, array_search($level, self::LEVELS) + 1) as $below) {
            $values[$below] = null;
        }

        return self::make(...$values);
    }

    public function parent(): ?self
    {
        $level = $this->level();
        if ($level === 'australia') {
            return null;
        }

        $values = $this->values();
        $values[$level] = null;

        return self::make(...$values);
    }

    /** @return list<self> Australia first, this area last. */
    public function trail(): array
    {
        $trail = [$this];
        for ($area = $this->parent(); $area; $area = $area->parent()) {
            array_unshift($trail, $area);
        }

        return $trail;
    }

    /** @return array{state: ?string, city: ?string, council: ?string, suburb: ?string} */
    public function values(): array
    {
        return ['state' => $this->state, 'city' => $this->city, 'council' => $this->council, 'suburb' => $this->suburb];
    }

    /** Query values for the club and venue lists: state=VIC&city=Melbourne… */
    public function query(): array
    {
        return array_filter($this->values());
    }

    public function url(array $query = []): string
    {
        if ($this->level() === 'australia') {
            return route('play', $query);
        }

        return route('play.area', [...array_map(fn (?string $value) => $value === null ? null : Str::slug($value), $this->values()), ...$query]);
    }

    /** Listings in this area. */
    public function scope(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        foreach ($this->query() as $column => $value) {
            $query->whereRaw("lower({$table}.{$column}) = ?", [mb_strtolower($value)]);
        }

        return $query;
    }

    /**
     * The names one level down: cities in a state, councils in a city, suburbs in
     * a council. Taken from the directory and the suburb lookup together, so a
     * place keeps its page even before anything is listed there.
     */
    public function childNames(string $level): Collection
    {
        $names = collect([Venue::class, Club::class, Locality::class])
            ->flatMap(fn (string $model) => $this->scope($model::query())->whereNotNull($level)->distinct()->pluck($level));

        return $names->filter()->unique(fn (string $name) => Str::slug($name))->values();
    }

    /**
     * The middle of the area, from the suburb lookup, or from its venues when the
     * lookup has nothing. Null for Australia.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function centre(): ?array
    {
        if ($this->level() === 'australia') {
            return null;
        }

        foreach ([Locality::class, Venue::class] as $model) {
            $point = $this->scope($model::query())->whereNotNull('latitude')->whereNotNull('longitude')
                ->selectRaw('avg(latitude) as lat, avg(longitude) as lng')->toBase()->first();
            if ($point?->lat !== null) {
                return ['lat' => round((float) $point->lat, 4), 'lng' => round((float) $point->lng, 4)];
            }
        }

        return null;
    }
}
