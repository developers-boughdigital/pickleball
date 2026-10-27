<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/dashboard', '/admin/users', '/admin/roles', '/admin/permissions', '/admin/activity-log'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_home_redirects_signed_in_users_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/dashboard');
    }

    public function test_dashboard_shows_real_counts_activity_and_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $me = User::factory()->create(['name' => 'Sokha Admin'])->assignRole(Role::SUPER_ADMIN);
        User::factory()->count(2)->create();
        $this->actingAs($me);
        ActivityLog::record('role.updated', 'Updated role Viewer');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Good')
            ->assertSee('Sokha')
            ->assertSeeInOrder(['Users', '3'])
            ->assertSee('updated role Viewer')
            ->assertSee('Newest users')
            ->assertSeeInOrder(['People per role', 'Platform admin'])
            ->assertDontSee('Club Night');
    }

    public function test_dashboard_works_with_an_empty_database(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('No activity yet')
            ->assertSee('No roles yet.');
    }

    public function test_activity_log_lists_filters_and_shows_details(): void
    {
        $me = User::factory()->create(['name' => 'Megan Clarke']);
        $this->actingAs($me);

        ActivityLog::record('role.updated', 'Updated role Viewer', null, 'Viewer', ['name' => ['from' => 'Staff', 'to' => 'Viewer']]);
        $failed = ActivityLog::record('auth.login_failed', 'Failed sign-in: wrong email or password', succeeded: false, actor: null, actorName: 'who@example.com');

        $this->get('/admin/activity-log')
            ->assertOk()
            ->assertSee('Updated role Viewer')
            ->assertSee('who@example.com')
            ->assertSee('Showing 1–2 of 2 events');

        $this->get('/admin/activity-log?result=failed')->assertSee('Failed sign-in')->assertDontSee('Updated role Viewer</td>', false);
        $this->get('/admin/activity-log?action=role.updated')->assertSee('Updated role Viewer')->assertDontSee('who@example.com</td>', false);
        $this->get('/admin/activity-log?search=viewer')->assertSee('Updated role Viewer')->assertDontSee('Failed sign-in:', false);

        // Details: the before/after table for the selected event
        $updated = ActivityLog::where('action', 'role.updated')->first();
        $this->get("/admin/activity-log?event={$updated->id}")->assertSeeInOrder(['Field', 'Before', 'After', 'Name', 'Staff', 'Viewer']);
        // A failed sign-in for an unknown email is attributed to the email that was typed.
        $this->get("/admin/activity-log?event={$failed->id}")->assertSeeInOrder(['Who', 'who@example.com'])->assertSee('badge-danger');
    }

    public function test_activity_log_date_range(): void
    {
        $this->actingAs(User::factory()->create());

        ActivityLog::record('role.updated', 'Old change')->forceFill(['created_at' => now()->subDays(40)])->save();
        ActivityLog::record('role.updated', 'Recent change');

        $this->get('/admin/activity-log')->assertSee('Recent change')->assertDontSee('Old change');
        $this->get('/admin/activity-log?range=90')->assertSee('Old change');
        $this->get('/admin/activity-log?range=1')->assertSee('Recent change')->assertDontSee('Old change');
    }

    public function test_sign_in_and_out_are_logged(): void
    {
        $user = User::factory()->create(['email' => 'megan@example.com']);

        $this->post('/login', ['email' => 'megan@example.com', 'password' => 'wrong']);
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);
        $this->post('/login', ['email' => 'megan@example.com', 'password' => 'password']);
        $this->post('/logout');

        $this->assertSame(
            ['auth.login_failed', 'auth.login_failed', 'auth.login', 'auth.logout'],
            ActivityLog::orderBy('id')->pluck('action')->all(),
        );

        $failed = ActivityLog::where('action', 'auth.login_failed')->orderBy('id')->get();
        $this->assertSame($user->id, $failed[0]->user_id);
        $this->assertNull($failed[1]->user_id);
        $this->assertSame('nobody@example.com', $failed[1]->actor_name);
        $this->assertFalse($failed[1]->succeeded);
    }
}
