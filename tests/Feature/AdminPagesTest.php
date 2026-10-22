<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/dashboard', '/admin/users', '/admin/roles', '/admin/activity-log'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_home_redirects_signed_in_users_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/dashboard');
    }

    public function test_dashboard_renders_with_user_name_and_initials(): void
    {
        $user = User::factory()->create(['name' => 'Sokha Admin']);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Sokha')
            ->assertSee('SA')
            ->assertSee('Bookings and check-ins')
            ->assertSee('Listing claims queue')
            ->assertSee('Tuesday Club Night')
            ->assertSee('Recent activity');
    }

    public function test_users_page_filters(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/users')->assertOk()->assertSee('Tom Nguyen')->assertSee('Banyule Pickleball Club');
        $this->get('/admin/users?status=Suspended')->assertOk()->assertSee('Mark Rossi')->assertDontSee('Tom Nguyen');
        $this->get('/admin/users?role=Coach')->assertOk()->assertSee('Priya Sharma')->assertDontSee('Tom Nguyen');
        $this->get('/admin/users?search=knox')->assertOk()->assertSee('Helen Brooks')->assertDontSee('Tom Nguyen');
    }

    public function test_roles_page_shows_selected_role_matrix(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/roles')->assertOk()->assertSee('Selected role: <span class="font-medium text-ink-950">Club admin</span>', false);
        $this->get('/admin/roles?role=session-host')->assertOk()->assertSee('Club session host')->assertSee('Club Night');
        $this->get('/admin/roles?type=venue')->assertOk()->assertSee('Venue manager')->assertDontSee('Seller</a>', false);
    }

    public function test_activity_log_selects_event_and_filters(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/activity-log')->assertOk()->assertSee('Approved Sarah Nguyen as club admin');
        $this->get('/admin/activity-log?event=4')->assertOk()->assertSee('Magic link expired');
        $this->get('/admin/activity-log?module=Levels')->assertOk()->assertSee('1 matching event')->assertDontSee('Refunded casual fee</td>', false);
    }
}
