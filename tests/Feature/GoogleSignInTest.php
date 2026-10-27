<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret']);
    }

    private function googleReturns(array $profile, bool $verified = true): void
    {
        $user = (new GoogleUser)
            ->map([
                'id' => 'google-123',
                'name' => 'Megan Clarke',
                'email' => 'megan@example.com',
                'avatar' => 'https://example.com/megan.jpg',
                ...$profile,
            ])
            ->setRaw(['email_verified' => $verified]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_button_only_shows_when_google_is_configured(): void
    {
        $this->get('/login')->assertSee('Continue with Google');
        $this->get('/register')->assertSee('Continue with Google');

        config(['services.google.client_id' => null]);
        $this->get('/login')->assertDontSee('Continue with Google');
        $this->get('/auth/google')->assertRedirect('/login')->assertSessionHasErrors('google');
    }

    public function test_redirects_to_google(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google')->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_new_google_user_is_registered_and_signed_in(): void
    {
        $this->googleReturns(['email' => 'Megan@Example.com']);

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $user = User::firstWhere('email', 'megan@example.com');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('auth.registered', ActivityLog::latest('id')->value('action'));
    }

    public function test_returning_google_user_is_signed_in(): void
    {
        $user = User::factory()->create(['google_id' => 'google-123', 'email' => 'megan@example.com']);
        $this->googleReturns(['avatar' => 'https://example.com/new.jpg']);

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
        $this->assertSame('https://example.com/new.jpg', $user->fresh()->avatar);
        $this->assertSame('Signed in with Google', ActivityLog::latest('id')->value('description'));
    }

    public function test_existing_email_account_is_linked_when_google_verified_it(): void
    {
        $user = User::factory()->create(['email' => 'megan@example.com']);
        $this->googleReturns([]);

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->fresh()->google_id);
    }

    public function test_existing_email_account_is_not_linked_when_unverified(): void
    {
        User::factory()->create(['email' => 'megan@example.com']);
        $this->googleReturns([], verified: false);

        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull(User::first()->google_id);
    }

    public function test_google_failure_returns_to_login(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andThrow(new RuntimeException('access_denied'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_google_only_user_cannot_sign_in_with_a_blank_password(): void
    {
        User::factory()->create(['email' => 'megan@example.com', 'password' => null, 'google_id' => 'google-123']);

        $this->post('/login', ['email' => 'megan@example.com', 'password' => ''])->assertSessionHasErrors();
        $this->post('/login', ['email' => 'megan@example.com', 'password' => 'anything'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
