<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Run migrations and seeders from the browser. Platform admins only (see routes).
 */
class DatabaseController extends Controller
{
    private Migrator $migrator;

    public function __construct()
    {
        $this->migrator = app('migrator');
    }

    public function index(): View
    {
        $migrations = $this->migrations();
        $connection = DB::connection();

        return view('admin.database', [
            'migrations' => $migrations,
            'seeders' => $this->seeders(),
            'pending' => $migrations->where('ran', false)->count(),
            'lastBatch' => $migrations->max('batch'),
            'connection' => [
                'driver' => $connection->getDriverName(),
                'database' => $connection->getDatabaseName(),
            ],
        ]);
    }

    public function migrate(): RedirectResponse
    {
        return $this->run('migrate', ['--force' => true], 'database.migrated', 'Ran pending migrations');
    }

    public function rollback(): RedirectResponse
    {
        return $this->run('migrate:rollback', ['--force' => true], 'database.rolled_back', 'Rolled back the last migration batch');
    }

    /**
     * Roll back one migration, whichever batch it was in. Artisan only rolls back
     * by batch or step, so this runs the file's down() itself.
     */
    public function rollbackMigration(Request $request): RedirectResponse
    {
        $name = $request->validate([
            'migration' => ['required', 'string', Rule::in($this->migrations()->where('ran', true)->pluck('name'))],
        ])['migration'];

        $started = microtime(true);

        try {
            // Our migrations are anonymous classes, so requiring the file hands back the instance.
            $migration = $this->migrator->getFilesystem()->getRequire(database_path("migrations/{$name}.php"));
            $connection = DB::connection($migration->getConnection());

            $down = function () use ($migration, $name) {
                $migration->down();
                $this->migrator->getRepository()->delete((object) ['migration' => $name]);
            };

            // Postgres can undo schema changes, so a down() that fails halfway leaves nothing behind.
            $migration->withinTransaction && $connection->getSchemaGrammar()->supportsSchemaTransactions()
                ? $connection->transaction($down)
                : $down();

            $succeeded = true;
            $output = sprintf('%s ... DONE (%d ms)', $name, (microtime(true) - $started) * 1000);
        } catch (Throwable $e) {
            report($e);
            $succeeded = false;
            $output = "{$name} ... FAILED\n\n{$e->getMessage()}";
        }

        return $this->respond('database.rolled_back', "Rolled back migration {$name}", $succeeded, $output, "down() in database/migrations/{$name}.php");
    }

    public function seed(Request $request): RedirectResponse
    {
        // Only seeders that actually live in database/seeders can be run.
        $seeder = $request->validate([
            'seeder' => ['required', 'string', Rule::in($this->seeders()->keys())],
        ])['seeder'];

        return $this->run('db:seed', ['--class' => $seeder, '--force' => true], 'database.seeded', "Ran seeder {$seeder}");
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function run(string $command, array $parameters, string $action, string $description): RedirectResponse
    {
        try {
            $exitCode = Artisan::call($command, $parameters);
            $output = Artisan::output();
        } catch (Throwable $e) {
            report($e);
            $exitCode = 1;
            $output = $e->getMessage();
        }

        $output = trim(preg_replace('/\e\[[\d;]*m/', '', $output)) ?: 'Nothing to do.';

        return $this->respond($action, $description, $exitCode === 0, $output, "php artisan {$command}".$this->flags($parameters));
    }

    /**
     * Log what happened and show its output on the page.
     */
    private function respond(string $action, string $description, bool $succeeded, string $output, string $command): RedirectResponse
    {
        // Rolling back activity_logs itself leaves nowhere to write; still show the result.
        rescue(fn () => ActivityLog::record($action, $description, succeeded: $succeeded, changes: [
            'output' => ['from' => null, 'to' => Str::limit($output, 500)],
        ]));

        return redirect()->route('admin.database')->with([
            'status' => $succeeded ? "{$description}." : "{$description} failed.",
            'console' => ['command' => $command, 'output' => $output, 'succeeded' => $succeeded],
        ]);
    }

    /**
     * Every migration file with whether it has run and in which batch.
     *
     * @return Collection<int, array{name: string, ran: bool, batch: ?int}>
     */
    private function migrations(): Collection
    {
        $batches = $this->migrator->repositoryExists()
            ? $this->migrator->getRepository()->getMigrationBatches()
            : [];

        return collect($this->migrator->getMigrationFiles(database_path('migrations')))
            ->keys()
            ->map(fn (string $name) => [
                'name' => $name,
                'ran' => isset($batches[$name]),
                'batch' => $batches[$name] ?? null,
            ])
            ->values();
    }

    /**
     * Seeder classes in database/seeders, keyed by class name.
     *
     * @return Collection<string, string>
     */
    private function seeders(): Collection
    {
        return collect(glob(database_path('seeders/*.php')))
            ->mapWithKeys(fn (string $path) => [pathinfo($path, PATHINFO_FILENAME) => Str::headline(Str::beforeLast(pathinfo($path, PATHINFO_FILENAME), 'Seeder'))])
            ->sortKeys();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function flags(array $parameters): string
    {
        return collect($parameters)
            ->map(fn ($value, $flag) => $value === true ? " {$flag}" : " {$flag}={$value}")
            ->implode('');
    }
}
