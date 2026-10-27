<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabasePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function signInAs(?string $role): User
    {
        $user = User::factory()->create();
        if ($role) {
            $user->assignRole($role);
        }
        $this->actingAs($user);

        return $user;
    }

    public function test_only_platform_admins_can_open_it(): void
    {
        $this->signInAs('Viewer');
        $this->get('/admin/database')->assertForbidden();
        $this->post('/admin/database/migrate')->assertForbidden();
        $this->post('/admin/database/seed', ['seeder' => 'RolesAndPermissionsSeeder'])->assertForbidden();

        $this->signInAs(null);
        $this->get('/admin/database')->assertForbidden();

        auth()->logout();
        $this->get('/admin/database')->assertRedirect('/login');
    }

    public function test_sidebar_link_only_shows_for_platform_admins(): void
    {
        $this->signInAs('Viewer');
        $this->get('/dashboard')->assertDontSee(route('admin.database'));

        $this->signInAs(Role::SUPER_ADMIN);
        $this->get('/dashboard')->assertSee(route('admin.database'));
    }

    public function test_page_lists_migrations_and_seeders(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->get('/admin/database')
            ->assertOk()
            ->assertSee('0001_01_01_000000_create_users_table')
            ->assertSee('Ran')
            ->assertSee('RolesAndPermissionsSeeder')
            ->assertSee('Up to date');
    }

    public function test_running_migrations_shows_output_and_is_logged(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->post('/admin/database/migrate')
            ->assertRedirect('/admin/database')
            ->assertSessionHas('console', fn ($console) => $console['succeeded'] && $console['command'] === 'php artisan migrate --force');

        $log = ActivityLog::firstWhere('action', 'database.migrated');
        $this->assertTrue($log->succeeded);
    }

    public function test_running_a_seeder(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->post('/admin/database/seed', ['seeder' => 'RolesAndPermissionsSeeder'])
            ->assertRedirect('/admin/database')
            ->assertSessionHas('status', 'Ran seeder RolesAndPermissionsSeeder.');

        $this->assertSame(1, ActivityLog::where('action', 'database.seeded')->count());
    }

    public function test_only_real_seeders_can_be_run(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->post('/admin/database/seed', ['seeder' => 'Illuminate\\Foundation\\Something'])->assertSessionHasErrors('seeder');
        $this->post('/admin/database/seed', ['seeder' => '../../etc/passwd'])->assertSessionHasErrors('seeder');
        $this->assertSame(0, ActivityLog::where('action', 'database.seeded')->count());
    }

    public function test_database_seeder_never_creates_the_test_login_in_production(): void
    {
        // Call the seeder directly; db:seed would stop to ask "are you sure?" in production.
        $seed = fn () => $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->__invoke();

        $this->app['env'] = 'production';
        $seed();
        $this->assertNull(User::firstWhere('email', 'test@example.com'));

        $this->app['env'] = 'local';
        $seed();
        $this->assertTrue(User::firstWhere('email', 'test@example.com')->hasRole(Role::SUPER_ADMIN));
    }

    public function test_rolling_back_a_single_migration_from_any_batch(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);
        $name = '2026_10_26_120000_add_module_and_description_to_permissions_table';

        $this->post('/admin/database/rollback-migration', ['migration' => $name])
            ->assertRedirect('/admin/database')
            ->assertSessionHas('status', "Rolled back migration {$name}.");

        $this->assertFalse(Schema::hasColumn('permissions', 'module'));
        $this->assertArrayNotHasKey($name, app('migrator')->getRepository()->getMigrationBatches());
        // Later migrations are left alone.
        $this->assertTrue(Schema::hasColumns('users', ['google_id', 'avatar']));

        $this->get('/admin/database')->assertSee('Pending');
    }

    public function test_a_failed_single_rollback_changes_nothing(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);
        $name = '2026_10_26_140000_add_google_sign_in_to_users_table';

        // Remove the index its down() expects to drop, so the rollback fails.
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['google_id']));

        $this->post('/admin/database/rollback-migration', ['migration' => $name])
            ->assertRedirect('/admin/database')
            ->assertSessionHas('console', fn ($console) => ! $console['succeeded'] && str_contains($console['output'], 'FAILED'));

        $this->assertTrue(Schema::hasColumns('users', ['google_id', 'avatar']));
        $this->assertArrayHasKey($name, app('migrator')->getRepository()->getMigrationBatches());
        $this->assertFalse(ActivityLog::firstWhere('action', 'database.rolled_back')->succeeded);
    }

    public function test_rolling_back_the_activity_log_table_still_shows_the_result(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->post('/admin/database/rollback-migration', ['migration' => '2026_10_26_130000_create_activity_logs_table'])
            ->assertRedirect('/admin/database')
            ->assertSessionHas('console', fn ($console) => $console['succeeded']);

        $this->assertFalse(Schema::hasTable('activity_logs'));
    }

    public function test_only_migrations_that_ran_can_be_rolled_back(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->post('/admin/database/rollback-migration', ['migration' => '../../config/app'])->assertSessionHasErrors('migration');
        $this->post('/admin/database/rollback-migration', ['migration' => '2099_01_01_000000_not_real'])->assertSessionHasErrors('migration');
        $this->assertSame(0, ActivityLog::where('action', 'database.rolled_back')->count());
    }

    public function test_rollback_calls_artisan_and_reports_failure(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        Artisan::shouldReceive('call')->once()->with('migrate:rollback', ['--force' => true])->andReturn(1);
        Artisan::shouldReceive('output')->once()->andReturn('Something went wrong');

        $this->post('/admin/database/rollback')
            ->assertRedirect('/admin/database')
            ->assertSessionHas('status', 'Rolled back the last migration batch failed.')
            ->assertSessionHas('console', fn ($console) => ! $console['succeeded'] && $console['output'] === 'Something went wrong');

        $this->assertFalse(ActivityLog::firstWhere('action', 'database.rolled_back')->succeeded);
    }
}
