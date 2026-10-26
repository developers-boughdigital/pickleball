<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function catalogSize(): int
    {
        return collect(RolesAndPermissionsSeeder::PERMISSIONS)->flatten()->count();
    }

    // Seeder

    public function test_seeder_covers_only_existing_features_and_is_safe_to_rerun(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame([Role::SUPER_ADMIN, 'Viewer'], Role::orderBy('id')->pluck('name')->all());
        $this->assertSame($this->catalogSize(), Permission::count());
        $this->assertEqualsCanonicalizing(
            ['Dashboard', 'Users', 'Roles', 'Permissions', 'Activity Log', 'System Settings'],
            Permission::modules()->all(),
        );

        $delete = Permission::findByName('users.delete');
        $this->assertSame('Users', $delete->module);
        $this->assertSame('Delete users', $delete->description);

        $this->assertTrue(Role::findByName(Role::SUPER_ADMIN)->hasPermissionTo('settings.update'));
        $this->assertTrue(Role::findByName('Viewer')->hasPermissionTo('users.view'));
        $this->assertFalse(Role::findByName('Viewer')->hasPermissionTo('users.delete'));
    }

    // Roles

    public function test_index_lists_roles_linking_to_their_edit_page(): void
    {
        $viewer = Role::findByName('Viewer');

        $this->get('/admin/roles')
            ->assertOk()
            ->assertSee(Role::SUPER_ADMIN)
            ->assertSee('href="'.route('admin.roles.edit', $viewer).'" class="font-medium hover:underline">Viewer</a>', false);

        $this->get('/admin/roles?search=viewer')->assertSee('>Viewer</a>', false)->assertDontSee('>Platform admin</a>', false);
    }

    public function test_role_form_lists_permissions_grouped_by_module_with_their_purpose(): void
    {
        $this->get('/admin/roles/create')
            ->assertOk()
            ->assertSeeInOrder(['Activity Log', 'activity.view', 'View the audit log'])
            ->assertSee('Select all');
    }

    public function test_role_can_be_created_and_it_is_logged(): void
    {
        $this->post('/admin/roles', [
            'name' => 'Support',
            'description' => 'Helps people with their accounts',
            'permissions' => ['users.view', 'users.update'],
        ])->assertRedirect('/admin/roles');

        $role = Role::findByName('Support');
        $this->assertSame('Helps people with their accounts', $role->description);
        $this->assertEqualsCanonicalizing(['users.view', 'users.update'], $role->permissions->pluck('name')->all());

        $this->assertSame('Created role Support', ActivityLog::firstWhere('action', 'role.created')->description);
    }

    public function test_role_validation(): void
    {
        $this->post('/admin/roles', ['name' => 'Viewer', 'permissions' => ['nope.view']])
            ->assertSessionHasErrors(['name', 'permissions.0']);

        $this->post('/admin/roles', ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_role_can_be_updated_and_changes_are_logged(): void
    {
        $role = Role::findByName('Viewer');

        $this->get("/admin/roles/{$role->id}/edit")->assertOk()->assertSee('value="Viewer"', false);

        $this->put("/admin/roles/{$role->id}", ['name' => 'Read only', 'permissions' => ['users.view', 'users.create']])
            ->assertRedirect('/admin/roles');

        $role->refresh();
        $this->assertSame('Read only', $role->name);
        $this->assertEqualsCanonicalizing(['users.view', 'users.create'], $role->permissions->pluck('name')->all());

        $changes = ActivityLog::firstWhere('action', 'role.updated')->changes;
        $this->assertSame(['from' => 'Viewer', 'to' => 'Read only'], $changes['name']);
        $this->assertSame(['users.create'], $changes['permissions added']['to']);
        $this->assertContains('settings.view', $changes['permissions removed']['from']);
    }

    public function test_role_can_be_deleted_and_is_removed_from_users(): void
    {
        $role = Role::findByName('Viewer');
        $user = User::factory()->create()->assignRole($role);

        $this->delete("/admin/roles/{$role->id}")
            ->assertRedirect('/admin/roles')
            ->assertSessionHas('status', 'Role “Viewer” deleted. It was removed from 1 user.');

        $this->assertNull(Role::where('name', 'Viewer')->first());
        $this->assertFalse($user->fresh()->hasRole('Viewer'));
    }

    public function test_platform_admin_is_protected(): void
    {
        $admin = Role::findByName(Role::SUPER_ADMIN);

        $this->delete("/admin/roles/{$admin->id}");
        $this->assertNotNull($admin->fresh());
        $this->assertFalse(ActivityLog::firstWhere('action', 'role.deleted')->succeeded);

        $this->put("/admin/roles/{$admin->id}", ['name' => 'Boss'])->assertSessionHasErrors('name');

        // Unticking everything still leaves the super admin with every permission.
        $this->put("/admin/roles/{$admin->id}", ['name' => Role::SUPER_ADMIN, 'permissions' => []]);
        $this->assertSame(Permission::count(), $admin->fresh()->permissions()->count());
    }

    // Permissions

    public function test_permissions_index_shows_module_permission_and_purpose(): void
    {
        $this->get('/admin/permissions')
            ->assertOk()
            ->assertSeeInOrder(['Users', 'users.create', 'Create users'])
            ->assertSee("Showing {$this->catalogSize()} of {$this->catalogSize()} permissions.");

        $this->get('/admin/permissions?module=Roles')->assertSee('roles.update')->assertDontSee('users.view');
        $this->get('/admin/permissions?search=settings')->assertSee('settings.update')->assertDontSee('users.view');
    }

    public function test_permission_can_be_created_in_a_new_module(): void
    {
        $this->get('/admin/permissions/create')->assertOk()->assertSee('Add permission');

        $this->post('/admin/permissions', [
            'module' => 'Customers',
            'name' => ' Customers.Export ',
            'description' => 'Export customers',
        ])->assertRedirect('/admin/permissions')->assertSessionHas('status', 'Permission customers.export created.');

        $permission = Permission::findByName('customers.export');
        $this->assertSame('Customers', $permission->module);
        $this->assertSame('Export customers', $permission->description);

        // The super admin picks it up automatically, and it appears on the role form.
        $this->assertTrue(Role::findByName(Role::SUPER_ADMIN)->hasPermissionTo('customers.export'));
        $this->get('/admin/roles/create')->assertSeeInOrder(['Customers', 'customers.export', 'Export customers']);
        $this->assertSame(1, ActivityLog::where('action', 'permission.created')->count());
    }

    public function test_permission_validation(): void
    {
        $this->post('/admin/permissions', ['module' => '', 'name' => 'no-dot'])->assertSessionHasErrors(['module', 'name']);
        $this->post('/admin/permissions', ['module' => 'Users', 'name' => 'users.view'])->assertSessionHasErrors('name');
        $this->post('/admin/permissions', ['module' => 'Users', 'name' => 'users.has space'])->assertSessionHasErrors('name');
    }

    public function test_permission_can_be_updated(): void
    {
        $permission = Permission::create(['name' => 'orders.approve', 'guard_name' => 'web', 'module' => 'Orders']);

        $this->get("/admin/permissions/{$permission->id}/edit")->assertOk()->assertSee('value="Orders"', false);

        $this->put("/admin/permissions/{$permission->id}", [
            'module' => 'Orders',
            'name' => 'orders.approve',
            'description' => 'Approve orders',
        ])->assertRedirect('/admin/permissions');

        $this->assertSame('Approve orders', $permission->fresh()->description);
        $this->assertSame(['from' => null, 'to' => 'Approve orders'], ActivityLog::firstWhere('action', 'permission.updated')->changes['description']);
    }

    public function test_permission_can_be_deleted_and_is_removed_from_roles(): void
    {
        $permission = Permission::findByName('users.delete');

        $this->delete("/admin/permissions/{$permission->id}")
            ->assertRedirect('/admin/permissions')
            ->assertSessionHas('status', 'Permission users.delete deleted. It was removed from 1 role.');

        $this->assertNull(Permission::where('name', 'users.delete')->first());
        $this->assertSame($this->catalogSize() - 1, Permission::count());
    }
}
