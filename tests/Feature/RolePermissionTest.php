<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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

    public function test_seeder_creates_the_brief_roles_and_is_safe_to_rerun(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(7, Role::count());
        $this->assertSame(32, Permission::count());
        $this->assertTrue(Role::findByName('Platform admin')->hasPermissionTo('payments.approve'));
        $this->assertFalse(Role::findByName('Club session host')->hasPermissionTo('payments.view'));
    }

    public function test_index_lists_roles_and_shows_the_selected_matrix(): void
    {
        $host = Role::findByName('Club session host');

        $this->get('/admin/roles')
            ->assertOk()
            ->assertSee('Venue manager')
            ->assertSee('Selected role: <span class="font-medium text-ink-950">Club admin</span>', false);

        $this->get("/admin/roles?role={$host->id}")->assertSee('Selected role: <span class="font-medium text-ink-950">Club session host</span>', false);

        $this->get('/admin/roles?search=coach')->assertSee('Coach')->assertDontSee('>Seller</a>', false);
    }

    public function test_role_can_be_created_with_permissions(): void
    {
        $this->get('/admin/roles/create')->assertOk()->assertSee('Create role');

        $this->post('/admin/roles', [
            'name' => 'Treasurer',
            'description' => 'Takings and refunds',
            'permissions' => ['payments.view', 'payments.approve'],
        ])->assertRedirect();

        $role = Role::findByName('Treasurer');
        $this->assertSame('Takings and refunds', $role->description);
        $this->assertEqualsCanonicalizing(['payments.view', 'payments.approve'], $role->permissions->pluck('name')->all());
    }

    public function test_role_validation(): void
    {
        $this->post('/admin/roles', ['name' => 'Coach', 'permissions' => ['nope.view']])
            ->assertSessionHasErrors(['name', 'permissions.0']);

        $this->post('/admin/roles', ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_role_can_be_updated(): void
    {
        $role = Role::findByName('Seller');

        $this->get("/admin/roles/{$role->id}/edit")->assertOk()->assertSee('value="Seller"', false);

        $this->put("/admin/roles/{$role->id}", [
            'name' => 'Retailer',
            'permissions' => ['shop.view'],
        ])->assertRedirect("/admin/roles?role={$role->id}");

        $role->refresh();
        $this->assertSame('Retailer', $role->name);
        $this->assertSame(['shop.view'], $role->permissions->pluck('name')->all());
    }

    public function test_role_can_be_deleted_and_is_removed_from_users(): void
    {
        $role = Role::findByName('Coach');
        $user = User::factory()->create()->assignRole($role);

        $this->delete("/admin/roles/{$role->id}")
            ->assertRedirect('/admin/roles')
            ->assertSessionHas('status', 'Role “Coach” deleted. It was removed from 1 user.');

        $this->assertNull(Role::where('name', 'Coach')->first());
        $this->assertFalse($user->fresh()->hasRole('Coach'));
    }

    public function test_platform_admin_is_protected(): void
    {
        $admin = Role::findByName(Role::SUPER_ADMIN);

        $this->delete("/admin/roles/{$admin->id}");
        $this->assertNotNull($admin->fresh());

        $this->put("/admin/roles/{$admin->id}", ['name' => 'Boss'])->assertSessionHasErrors('name');

        // Unticking everything still leaves the super admin with every permission.
        $this->put("/admin/roles/{$admin->id}", ['name' => Role::SUPER_ADMIN, 'permissions' => []]);
        $this->assertSame(Permission::count(), $admin->fresh()->permissions()->count());
    }

    public function test_permissions_page_and_adding_a_module(): void
    {
        $this->get('/admin/permissions')->assertOk()->assertSee('Club Night')->assertSee('club-night');

        $this->post('/admin/permissions', [
            'module' => 'reports',
            'actions' => ['view'],
            'custom_action' => 'export',
        ])->assertRedirect('/admin/permissions')->assertSessionHas('status', 'Added 2 permissions to Reports.');

        $this->assertNotNull(Permission::where('name', 'reports.export')->first());
        $this->assertTrue(Role::findByName(Role::SUPER_ADMIN)->hasPermissionTo('reports.export'));

        // New custom actions show up as a column on the role form.
        $this->get('/admin/roles/create')->assertSee('Export');
    }

    public function test_permission_validation(): void
    {
        $this->post('/admin/permissions', ['module' => 'Bad Name!', 'actions' => []])->assertSessionHasErrors('module');
        $this->post('/admin/permissions', ['module' => 'reports'])->assertSessionHasErrors('actions');
    }

    public function test_permission_and_module_can_be_deleted(): void
    {
        $permission = Permission::findByName('shop.approve');

        $this->delete("/admin/permissions/{$permission->id}")->assertRedirect();
        $this->assertNull(Permission::where('name', 'shop.approve')->first());

        $this->delete('/admin/permission-modules/shop')->assertRedirect('/admin/permissions')
            ->assertSessionHas('status', 'Deleted the Shop module (3 permissions).');
        $this->assertSame(0, Permission::where('name', 'like', 'shop.%')->count());
        $this->assertSame(28, Permission::count());
    }
}
