<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Spatie's role with an optional description.
 *
 * @property string|null $description
 */
class Role extends SpatieRole
{
    /**
     * The role that must always exist with every permission, so an admin can't
     * lock everyone out of the platform.
     */
    public const SUPER_ADMIN = 'Platform admin';

    public function isProtected(): bool
    {
        return $this->name === self::SUPER_ADMIN;
    }
}
