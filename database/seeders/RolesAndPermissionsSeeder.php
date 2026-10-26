<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The platform roles from the brief. Safe to run more than once: existing roles
 * keep any permission changes made in the admin, only missing ones are added.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const MODULES = ['directory', 'clubs', 'sessions', 'club-night', 'members', 'payments', 'levels', 'shop'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MODULES as $module) {
            foreach (['view', 'create', 'edit', 'approve'] as $action) {
                Permission::findOrCreate("{$module}.{$action}", 'web');
            }
        }

        foreach ($this->roles() as $name => [$description, $grants]) {
            $role = Role::firstOrNew(['name' => $name, 'guard_name' => 'web']);

            if ($role->exists) {
                continue;
            }

            $role->fill(['description' => $description])->save();
            $role->syncPermissions($this->expand($grants));
        }
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    private function roles(): array
    {
        $all = array_fill_keys(self::MODULES, 'view,create,edit,approve');

        return [
            Role::SUPER_ADMIN => ['Approve claims, moderate content, manage listings, refunds and support', $all],
            'Club admin' => ['Club profile, sessions, events, members and payments; runs Club Night', [
                'directory' => 'view', 'clubs' => 'view,create,edit', 'sessions' => 'view,create,edit,approve',
                'club-night' => 'view,create,edit', 'members' => 'view,create,edit,approve',
                'payments' => 'view,edit', 'levels' => 'view,edit,approve', 'shop' => 'view',
            ]],
            'Club session host' => ['Check-in, court rotation and scores on the night. No money or member data', [
                'directory' => 'view', 'clubs' => 'view', 'sessions' => 'view', 'club-night' => 'view,create,edit', 'shop' => 'view',
            ]],
            'Coach' => ['Coach profile, lessons and clinics, enquiries, assess player levels', [
                'directory' => 'view', 'clubs' => 'view', 'sessions' => 'view,create,edit', 'levels' => 'view,edit,approve', 'shop' => 'view',
            ]],
            'Venue manager' => ['Venue listing, courts, opening hours and prices', [
                'directory' => 'view,edit', 'clubs' => 'view', 'sessions' => 'view', 'shop' => 'view',
            ]],
            'Seller' => ['Product listings in the shop (Phase 2)', [
                'directory' => 'view', 'shop' => 'view,create,edit',
            ]],
            'Player' => ['Profile and level, join sessions, pay, follow clubs', [
                'directory' => 'view', 'clubs' => 'view', 'sessions' => 'view,create', 'club-night' => 'view,edit',
                'payments' => 'view', 'levels' => 'view,create', 'shop' => 'view',
            ]],
        ];
    }

    /**
     * ['clubs' => 'view,edit'] => ['clubs.view', 'clubs.edit']
     *
     * @param  array<string, string>  $grants
     * @return list<string>
     */
    private function expand(array $grants): array
    {
        return collect($grants)
            ->flatMap(fn (string $actions, string $module) => array_map(fn ($action) => "{$module}.{$action}", explode(',', $actions)))
            ->values()
            ->all();
    }
}
