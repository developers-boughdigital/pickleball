<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the parts of the admin that exist today, plus two starter roles.
 *
 * Safe to run more than once: permissions are created or have their module and
 * purpose refreshed; roles that already exist keep whatever was changed in the
 * admin, only missing roles are created.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Module => [permission name => purpose].
     */
    public const PERMISSIONS = [
        'Dashboard' => [
            'dashboard.view' => 'View the overview dashboard',
        ],
        'Users' => [
            'users.view' => 'View users',
            'users.create' => 'Create users',
            'users.update' => 'Edit users and their roles',
            'users.delete' => 'Delete users',
        ],
        'Roles' => [
            'roles.view' => 'View roles',
            'roles.create' => 'Create roles',
            'roles.update' => 'Edit roles and their permissions',
            'roles.delete' => 'Delete roles',
        ],
        'Permissions' => [
            'permissions.view' => 'View permissions',
            'permissions.create' => 'Add permissions',
            'permissions.update' => 'Edit permissions',
            'permissions.delete' => 'Delete permissions',
        ],
        'Activity Log' => [
            'activity.view' => 'View the audit log',
        ],
        'System Settings' => [
            'settings.view' => 'View system settings',
            'settings.update' => 'Change system settings',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $module => $permissions) {
            foreach ($permissions as $name => $description) {
                Permission::updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['module' => $module, 'description' => $description],
                );
            }
        }

        $all = collect(self::PERMISSIONS)->flatMap(fn (array $permissions) => array_keys($permissions));

        $roles = [
            Role::SUPER_ADMIN => ['Full access to everything, including roles and permissions', $all],
            'Viewer' => ['Can look around the admin but not change anything', $all->filter(fn (string $name) => str_ends_with($name, '.view'))],
        ];

        foreach ($roles as $name => [$description, $permissions]) {
            $role = Role::firstOrNew(['name' => $name, 'guard_name' => 'web']);

            if ($role->exists) {
                continue;
            }

            $role->fill(['description' => $description])->save();
            $role->syncPermissions($permissions->values()->all());
        }
    }
}
