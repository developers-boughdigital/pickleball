<?php

namespace Tests\Feature;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        // Point Laravel at an empty folder so the app looks uninstalled, without
        // touching the real .env.
        $this->dir = storage_path('framework/testing/installer-'.uniqid());
        File::ensureDirectoryExists($this->dir);
        $this->app->useEnvironmentPath($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_every_page_redirects_to_the_installer_until_installed(): void
    {
        $this->get('/')->assertRedirect('/install');
        $this->get('/dashboard')->assertRedirect('/install');
        $this->get('/login')->assertRedirect('/install');
    }

    public function test_installer_shows_requirements(): void
    {
        $this->get('/install')
            ->assertOk()
            ->assertSee('Welcome')
            ->assertSee('server checks passed');
    }

    public function test_installer_is_locked_once_env_exists(): void
    {
        File::put($this->dir.'/.env', 'APP_NAME=PickleBall');

        $this->get('/install')->assertNotFound();
        $this->postJson('/install', [])->assertNotFound();
    }

    public function test_database_check_reports_connection_errors(): void
    {
        $this->postJson('/install/database', [
            'db_driver' => 'pgsql',
            'db_host' => '127.0.0.1',
            'db_port' => 1,
            'db_database' => 'nope',
            'db_username' => 'nobody',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Could not connect to the database'))
            ->assertJsonValidationErrors('db_connection');
    }

    public function test_install_validates_the_form(): void
    {
        $this->postJson('/install', ['db_driver' => 'sqlite'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['app_name', 'app_url', 'db_database', 'admin_email', 'admin_password']);

        $this->assertFileDoesNotExist($this->dir.'/.env');
    }

    public function test_full_install_writes_env_migrates_and_creates_the_admin(): void
    {
        $database = $this->dir.'/club.sqlite';

        $this->postJson('/install', [
            'app_name' => 'Banyule Pickleball',
            'app_url' => 'https://play.example.com/',
            'app_env' => 'production',
            'db_driver' => 'sqlite',
            'db_database' => $database,
            'admin_name' => 'Megan Clarke',
            'admin_email' => 'megan@example.com',
            'admin_password' => 'correct-horse',
            'admin_password_confirmation' => 'correct-horse',
        ])->assertOk()->assertJsonPath('message', 'Installed.');

        // Read it back the way Laravel will, so quoting rules are covered too.
        $env = Dotenv::parse(File::get($this->dir.'/.env'));

        $this->assertSame('Banyule Pickleball', $env['APP_NAME']);
        $this->assertSame('production', $env['APP_ENV']);
        $this->assertSame('false', $env['APP_DEBUG']);
        $this->assertSame('https://play.example.com', $env['APP_URL']);
        $this->assertMatchesRegularExpression('/^base64:[A-Za-z0-9+\/]{43}=$/', $env['APP_KEY']);
        $this->assertSame('sqlite', $env['DB_CONNECTION']);
        $this->assertSame($database, $env['DB_DATABASE']);

        $admin = (new PDO('sqlite:'.$database))->query("select name from users where email = 'megan@example.com'")->fetchColumn();
        $this->assertSame('Megan Clarke', $admin);

        $this->get('/install')->assertNotFound();
    }
}
