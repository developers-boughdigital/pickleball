<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_link_is_on_home_page(): void
    {
        $this->get('/')->assertOk()->assertSee(route('login'));
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back');
    }

    public function test_user_can_log_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_log_in_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_page_is_never_cached(): void
    {
        $this->assertStringContainsString('no-store', $this->get('/login')->headers->get('Cache-Control'));
    }

    public function test_an_expired_login_form_goes_back_with_a_message_instead_of_419(): void
    {
        $this->checkCsrfTokens();

        $this->from('/login')->post('/login', ['email' => 'sam@example.com', 'password' => 'secret', '_token' => 'stale'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'The page had expired, so please try again.'])
            ->assertSessionHasInput('email', 'sam@example.com')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_an_expired_login_form_sent_after_signing_in_goes_to_the_dashboard(): void
    {
        $this->checkCsrfTokens();

        // e.g. the form was submitted twice: the first one already signed in.
        $this->actingAs(User::factory()->create())
            ->post('/login', ['_token' => 'stale'])
            ->assertRedirect('/dashboard');

        // Logging out with an old token once already signed out just goes home.
        auth()->logout();
        $this->post('/logout', ['_token' => 'stale'])->assertRedirect('/');

        // Other forms still get the normal 419.
        $this->actingAs(User::factory()->create())->put('/admin/settings', ['_token' => 'stale'])->assertStatus(419);
    }

    /** CSRF checks are skipped while running tests; switch them back on. */
    private function checkCsrfTokens(): void
    {
        $this->app->instance('env', 'local');
    }
}
