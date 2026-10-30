<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\GoogleController;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private array $valid = [
        'app_name' => 'Riverside Club',
        'app_url' => 'https://club.example.com/',
        'timezone' => 'Asia/Phnom_Penh',
        'google_client_id' => '',
        'google_client_secret' => '',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        // Logos uploaded by these tests land in the real public folder.
        foreach (File::glob(public_path('uploads/logo-*')) as $file) {
            if (filemtime($file) >= $this->startedAt) {
                File::delete($file);
            }
        }

        parent::tearDown();
    }

    private int $startedAt;

    private function signInAs(?string $role): User
    {
        $this->startedAt ??= time() - 1;
        $user = User::factory()->create();
        if ($role) {
            $user->assignRole($role);
        }
        $this->actingAs($user);

        return $user;
    }

    public function test_viewers_can_look_but_not_change(): void
    {
        $this->signInAs('Viewer');

        $this->get('/admin/settings')->assertOk()->assertSee('You can view these settings')->assertDontSee('Save settings');
        $this->put('/admin/settings', $this->valid)->assertForbidden();
        $this->get('/dashboard')->assertSee(route('admin.settings'));

        $this->signInAs(null);
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/dashboard')->assertDontSee(route('admin.settings'));
    }

    public function test_saving_settings_applies_them_and_logs_the_change(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->put('/admin/settings', $this->valid)
            ->assertRedirect('/admin/settings')
            ->assertSessionHas('status', 'Settings saved.');

        $this->assertSame('Riverside Club', config('app.name'));
        $this->assertSame('https://club.example.com', config('app.url'));
        $this->assertSame('Asia/Phnom_Penh', date_default_timezone_get());

        // Sidebar shows the new name, last word in gold.
        $this->get('/dashboard')->assertSee('Riverside<span', false);

        $log = ActivityLog::firstWhere('action', 'settings.updated');
        $this->assertSame('Riverside Club', $log->changes['app_name']['to']);

        // Saving again without changes isn't logged twice.
        $this->put('/admin/settings', $this->valid)->assertSessionHas('status', 'Nothing to change.');
        $this->assertSame(1, ActivityLog::where('action', 'settings.updated')->count());
    }

    public function test_saved_settings_override_config_on_every_request(): void
    {
        Setting::put(['app_name' => 'Saved Name', 'google_client_id' => 'id-123']);
        config(['app.name' => 'From env']);

        Setting::applyToConfig();

        $this->assertSame('Saved Name', config('app.name'));
        $this->assertSame('id-123', config('services.google.client_id'));
    }

    public function test_google_secret_is_encrypted_and_never_shown(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->put('/admin/settings', [...$this->valid, 'google_client_id' => 'client-1', 'google_client_secret' => 'GOCSPX-super-secret']);

        $this->assertTrue(GoogleController::enabled());
        $stored = DB::table('settings')->where('key', 'google_client_secret')->value('value');
        $this->assertStringNotContainsString('GOCSPX', $stored);

        $this->get('/admin/settings')->assertDontSee('GOCSPX-super-secret')->assertSee('Saved. Leave blank to keep it');
        $this->assertStringNotContainsString('GOCSPX', json_encode(ActivityLog::latest('id')->first()->changes));

        // Blank keeps it...
        $this->put('/admin/settings', [...$this->valid, 'google_client_id' => 'client-1']);
        $this->assertSame('GOCSPX-super-secret', config('services.google.client_secret'));

        // ...and clearing the client ID turns Google off entirely.
        $this->put('/admin/settings', $this->valid);
        $this->assertNull(Setting::find('google_client_secret')->value);
        $this->assertFalse(GoogleController::enabled());
    }

    public function test_google_redirect_uri_can_be_changed(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->get('/admin/settings')->assertSee(route('auth.google.callback'));

        $this->put('/admin/settings', [...$this->valid, 'google_redirect' => 'https://club.example.com/auth/google/callback'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://club.example.com/auth/google/callback', config('services.google.redirect'));

        // Blank goes back to this site's callback.
        $this->put('/admin/settings', $this->valid);
        $this->assertSame(route('auth.google.callback'), config('services.google.redirect'));

        $this->put('/admin/settings', [...$this->valid, 'google_redirect' => 'https://evil.example.com/steal'])
            ->assertSessionHasErrors('google_redirect');
        $this->assertSame(route('auth.google.callback'), config('services.google.redirect'));
    }

    public function test_clearing_a_field_wins_over_env(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);
        config(['services.google.client_id' => 'from-env', 'services.google.client_secret' => 'from-env']);

        $this->put('/admin/settings', $this->valid);
        $this->assertFalse(GoogleController::enabled());

        // Next request boots from .env again; the saved blanks still win.
        config(['services.google.client_id' => 'from-env', 'services.google.client_secret' => 'from-env']);
        Setting::applyToConfig();
        $this->assertFalse(GoogleController::enabled());
    }

    public function test_logo_can_be_uploaded_and_removed(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->put('/admin/settings', [...$this->valid, 'logo' => UploadedFile::fake()->image('logo.png', 128, 128)])
            ->assertSessionHasNoErrors();

        $logo = Setting::find('logo')->value;
        $this->assertFileExists(public_path($logo));
        $this->get('/dashboard')->assertSee(asset($logo));

        $this->put('/admin/settings', [...$this->valid, 'remove_logo' => 1]);
        $this->assertFileDoesNotExist(public_path($logo));
        $this->assertNull(config('app.logo'));
        $this->get('/dashboard')->assertDontSee(asset($logo));
    }

    public function test_svg_and_large_logos_are_rejected(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->put('/admin/settings', [...$this->valid, 'logo' => $svg])->assertSessionHasErrors('logo');

        $big = UploadedFile::fake()->image('logo.png')->size(2048);
        $this->put('/admin/settings', [...$this->valid, 'logo' => $big])->assertSessionHasErrors('logo');

        $this->assertNull(Setting::find('logo'));
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->put('/admin/settings', [...$this->valid, 'app_name' => '', 'app_url' => 'javascript:alert(1)', 'timezone' => 'Mars/Olympus'])
            ->assertSessionHasErrors(['app_name', 'app_url', 'timezone']);

        $this->assertSame(0, Setting::count());
    }
}
