<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->me = User::factory()->create(['name' => 'Megan Clarke'])->assignRole(Role::SUPER_ADMIN);
        $this->actingAs($this->me);
    }

    public function test_index_lists_real_users_with_roles(): void
    {
        User::factory()->create(['name' => 'Tom Nguyen'])->assignRole('Viewer');
        User::factory()->create(['name' => 'Priya Sharma']);

        $this->get('/admin/users')
            ->assertOk()
            ->assertSee('Megan Clarke')
            ->assertSee('(you)')
            ->assertSeeInOrder(['Tom Nguyen', 'Viewer'])
            ->assertSee('Showing 1–3 of 3 users');

        $this->get('/admin/users?role=Viewer')->assertSee('Tom Nguyen')->assertDontSee('Priya Sharma');
        $this->get('/admin/users?role=none')->assertSee('Priya Sharma')->assertDontSee('Tom Nguyen');
        $this->get('/admin/users?search=priya')->assertSee('Priya Sharma')->assertDontSee('Tom Nguyen');
    }

    public function test_user_can_be_created_with_roles_and_it_is_logged(): void
    {
        $this->get('/admin/users/create')->assertOk()->assertSee('Add user')->assertSee('Viewer');

        $response = $this->post('/admin/users', [
            'name' => 'Dara Vong',
            'email' => 'DARA@Example.com',
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
            'roles' => ['Viewer'],
        ]);

        $dara = User::firstWhere('email', 'dara@example.com');
        $response->assertRedirect(route('admin.users.show', $dara));
        $this->assertTrue(Hash::check('a-good-password', $dara->password));
        $this->assertSame(['Viewer'], $dara->getRoleNames()->all());

        $log = ActivityLog::firstWhere('action', 'user.created');
        $this->assertSame('Megan Clarke', $log->actor_name);
        $this->assertSame(['from' => [], 'to' => ['Viewer']], $log->changes['roles']);
    }

    public function test_user_detail_page_shows_account_roles_permissions_and_activity(): void
    {
        $tom = User::factory()->create(['name' => 'Tom Nguyen', 'email' => 'tom@example.com'])->assignRole('Viewer');
        ActivityLog::record('user.updated', 'Updated user tom@example.com', $tom, $tom->name);

        $this->get('/admin/users')->assertSee(route('admin.users.show', $tom));

        $this->get("/admin/users/{$tom->id}")
            ->assertOk()
            ->assertSee('Tom Nguyen')
            ->assertSee('tom@example.com')
            ->assertSeeInOrder(['Signs in with', 'Password'])
            ->assertSeeInOrder(['Roles', 'Viewer'])
            ->assertSeeInOrder(['What they can do', 'Users', 'View users'])
            ->assertDontSee('Delete users')
            ->assertSeeInOrder(['Recent activity', 'Megan Clarke', 'updated user tom@example.com'])
            ->assertSee(route('admin.users.edit', $tom));
    }

    public function test_detail_page_for_yourself_has_no_delete_button(): void
    {
        $this->get("/admin/users/{$this->me->id}")
            ->assertOk()
            ->assertSee('(you)')
            ->assertDontSee('action="'.route('admin.users.destroy', $this->me).'"', false);
    }

    public function test_detail_page_for_a_user_without_a_role(): void
    {
        $nobody = User::factory()->create(['google_id' => 'g-1']);

        $this->get("/admin/users/{$nobody->id}")
            ->assertOk()
            ->assertSee('No role yet')
            ->assertSee('Password and Google')
            ->assertDontSee('What they can do');
    }

    public function test_user_validation(): void
    {
        $this->post('/admin/users', ['name' => '', 'email' => $this->me->email, 'password' => 'short', 'roles' => ['Nope']])
            ->assertSessionHasErrors(['name', 'email', 'password', 'roles.0']);
    }

    public function test_user_can_be_updated_keeping_password_when_blank(): void
    {
        $tom = User::factory()->create(['name' => 'Tom', 'email' => 'tom@example.com']);
        $oldHash = $tom->password;

        $this->get("/admin/users/{$tom->id}/edit")->assertOk()->assertSee('value="tom@example.com"', false);

        $this->put("/admin/users/{$tom->id}", [
            'name' => 'Tom Nguyen',
            'email' => 'tom@example.com',
            'password' => '',
            'roles' => [Role::SUPER_ADMIN],
        ])->assertRedirect("/admin/users/{$tom->id}");

        $tom->refresh();
        $this->assertSame('Tom Nguyen', $tom->name);
        $this->assertSame($oldHash, $tom->password);
        $this->assertTrue($tom->hasRole(Role::SUPER_ADMIN));

        $changes = ActivityLog::firstWhere('action', 'user.updated')->changes;
        $this->assertSame(['from' => 'Tom', 'to' => 'Tom Nguyen'], $changes['name']);
        $this->assertSame(['from' => [], 'to' => [Role::SUPER_ADMIN]], $changes['roles']);
        $this->assertArrayNotHasKey('password', $changes);
    }

    public function test_user_can_be_deleted_but_not_yourself(): void
    {
        $tom = User::factory()->create();

        $this->delete("/admin/users/{$tom->id}")->assertRedirect('/admin/users');
        $this->assertModelMissing($tom);
        $this->assertSame(1, ActivityLog::where('action', 'user.deleted')->count());

        $this->delete("/admin/users/{$this->me->id}")->assertSessionHas('status', 'You can’t delete your own account.');
        $this->assertModelExists($this->me);
    }
}
