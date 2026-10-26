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

        $this->post('/admin/users', [
            'name' => 'Dara Vong',
            'email' => 'DARA@Example.com',
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
            'roles' => ['Viewer'],
        ])->assertRedirect('/admin/users');

        $dara = User::firstWhere('email', 'dara@example.com');
        $this->assertTrue(Hash::check('a-good-password', $dara->password));
        $this->assertSame(['Viewer'], $dara->getRoleNames()->all());

        $log = ActivityLog::firstWhere('action', 'user.created');
        $this->assertSame('Megan Clarke', $log->actor_name);
        $this->assertSame(['from' => [], 'to' => ['Viewer']], $log->changes['roles']);
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
        ])->assertRedirect('/admin/users');

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
