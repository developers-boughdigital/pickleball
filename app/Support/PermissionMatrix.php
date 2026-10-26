<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Permissions are named "module.action" (e.g. "club-night.edit"). This turns a
 * flat list of names into the module × action grid shown on the roles pages.
 */
class PermissionMatrix
{
    /** Standard actions, always shown first and in this order. */
    public const ACTIONS = ['view', 'create', 'edit', 'approve'];

    /**
     * @param  iterable<string>  $names
     * @return Collection<string, list<string>> module => actions
     */
    public static function group(iterable $names): Collection
    {
        return collect($names)
            ->filter(fn (string $name) => str_contains($name, '.'))
            ->groupBy(fn (string $name) => Str::before($name, '.'))
            ->map(fn (Collection $names) => $names->map(fn (string $name) => Str::after($name, '.'))->values()->all());
    }

    /**
     * Every action used by any module: the standard four first, then extras A–Z.
     *
     * @param  Collection<string, list<string>>  $grouped
     * @return list<string>
     */
    public static function actions(Collection $grouped): array
    {
        $extra = $grouped->flatten()->unique()->diff(self::ACTIONS)->sort()->values()->all();

        return [...self::ACTIONS, ...$extra];
    }

    public static function name(string $module, string $action): string
    {
        return "{$module}.{$action}";
    }

    public static function label(string $module): string
    {
        return Str::headline($module);
    }
}
