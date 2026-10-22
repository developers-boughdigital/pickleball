<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_renders(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create your account');
    }

    public function test_new_user_can_register_and_is_logged_in(): void
    {
        $this->post('/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_validates_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertGuest();
    }

    public function test_authenticated_user_cannot_see_register_and_can_log_out(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/register')->assertRedirect('/dashboard');
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
