<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * A permission such as "users.view", grouped under a module ("Users") with a
 * plain-English purpose ("View users").
 *
 * @property string|null $module
 * @property string|null $description
 */
class Permission extends SpatiePermission
{
    /** The part after the dot: "users.view" => "view". */
    public function action(): string
    {
        return Str::after($this->name, '.');
    }

    /**
     * @return Collection<int, string>
     */
    public static function modules(): Collection
    {
        return static::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module');
    }
}
