<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * First-run setup, WordPress style: the app counts as installed once a .env
 * file exists. Until then every request is sent to /install.
 */
class Installer
{
    public const MIN_PHP = '8.3.0';

    public const EXTENSIONS = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'mbstring', 'openssl', 'pdo', 'session', 'tokenizer', 'xml'];

    public const DRIVERS = [
        'pgsql' => ['label' => 'PostgreSQL', 'extension' => 'pdo_pgsql', 'port' => 5432],
        'mysql' => ['label' => 'MySQL / MariaDB', 'extension' => 'pdo_mysql', 'port' => 3306],
        'sqlite' => ['label' => 'SQLite', 'extension' => 'pdo_sqlite', 'port' => null],
    ];

    public function installed(): bool
    {
        return file_exists(app()->environmentFilePath());
    }

    /**
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    public function requirements(): array
    {
        $checks = [[
            'label' => 'PHP '.self::MIN_PHP.' or newer',
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'detail' => 'You have PHP '.PHP_VERSION,
        ]];

        foreach (self::EXTENSIONS as $extension) {
            $checks[] = [
                'label' => "PHP {$extension} extension",
                'ok' => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? 'Enabled' : 'Missing, enable it in php.ini',
            ];
        }

        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache'), 'project folder (for .env)' => app()->environmentPath()] as $label => $path) {
            $checks[] = [
                'label' => "Writable {$label}",
                'ok' => is_writable($path),
                'detail' => is_writable($path) ? 'Writable' : "Make {$path} writable by the web server",
            ];
        }

        return $checks;
    }

    /**
     * Either a production build or a running Vite dev server (the "hot" file).
     */
    public function assetsBuilt(): bool
    {
        return file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
    }

    public function meetsRequirements(): bool
    {
        return collect($this->requirements())->every('ok');
    }

    /**
     * @return array<string, array{label: string, port: ?int, available: bool}>
     */
    public function drivers(): array
    {
        return collect(self::DRIVERS)
            ->map(fn (array $driver) => [
                'label' => $driver['label'],
                'port' => $driver['port'],
                'available' => extension_loaded($driver['extension']),
            ])
            ->all();
    }

    /**
     * Try to open a connection with the given settings. Throws when it can't.
     *
     * @param  array<string, mixed>  $db
     */
    public function testConnection(array $db): void
    {
        config(['database.connections.installer_check' => $this->connectionConfig($db)]);

        try {
            DB::connection('installer_check')->getPdo();
        } finally {
            DB::purge('installer_check');
        }
    }

    /**
     * Migrate the database, create the admin account, then write .env last so a
     * failure part way through leaves the installer ready to try again.
     *
     * @param  array<string, mixed>  $data
     */
    public function install(array $data): void
    {
        $driver = $data['db_driver'];

        config([
            "database.connections.{$driver}" => $this->connectionConfig($data),
            'database.default' => $driver,
        ]);
        DB::purge($driver);

        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException(trim(Artisan::output()) ?: 'Running the database migrations failed.');
        }

        (new RolesAndPermissionsSeeder)->run();

        User::updateOrCreate(
            ['email' => $data['admin_email']],
            ['name' => $data['admin_name'], 'password' => $data['admin_password']],
        )->assignRole(Role::SUPER_ADMIN);

        $this->writeEnvironmentFile($this->environmentValues($data));
    }

    public function defaultSqlitePath(): string
    {
        return database_path('database.sqlite');
    }

    /**
     * @param  array<string, mixed>  $db
     * @return array<string, mixed>
     */
    private function connectionConfig(array $db): array
    {
        $driver = $db['db_driver'];
        $base = config("database.connections.{$driver}");

        if ($driver === 'sqlite') {
            $path = $this->sqlitePath($db['db_database']);

            if (! file_exists($path)) {
                @mkdir(dirname($path), 0755, true);
                touch($path);
            }

            return [...$base, 'database' => $path];
        }

        return [
            ...$base,
            'host' => $db['db_host'],
            'port' => $db['db_port'],
            'database' => $db['db_database'],
            'username' => $db['db_username'],
            'password' => $db['db_password'] ?? '',
        ];
    }

    private function sqlitePath(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path);

        return $isAbsolute ? $path : base_path($path);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|bool>
     */
    private function environmentValues(array $data): array
    {
        $production = $data['app_env'] === 'production';

        $values = [
            'APP_NAME' => $data['app_name'],
            'APP_ENV' => $data['app_env'],
            'APP_KEY' => 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher'))),
            'APP_DEBUG' => ! $production,
            'APP_URL' => rtrim($data['app_url'], '/'),
            'LOG_LEVEL' => $production ? 'error' : 'debug',
            'DB_CONNECTION' => $data['db_driver'],
        ];

        if ($data['db_driver'] === 'sqlite') {
            return [...$values, 'DB_DATABASE' => $this->sqlitePath($data['db_database'])];
        }

        return [
            ...$values,
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => (string) $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'] ?? '',
        ];
    }

    /**
     * Fill in .env.example (uncommenting keys like "# DB_HOST=") and write it
     * atomically, so a half-written .env can never mark the app as installed.
     *
     * @param  array<string, string|bool>  $values
     */
    private function writeEnvironmentFile(array $values): void
    {
        $example = base_path('.env.example');
        $contents = file_exists($example) ? file_get_contents($example) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->formatValue($value);
            $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $contents, 1)
                : rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        $path = app()->environmentFilePath();
        $temporary = $path.'.installing';

        if (file_put_contents($temporary, $contents) === false || ! rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException("Could not write {$path}. Check the folder is writable.");
        }
    }

    private function formatValue(string|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === '' || preg_match('/^[\w.\/:@+=,-]+$/', $value)) {
            return $value;
        }

        // Single quotes are literal in dotenv (no ${VAR} expansion), so prefer them.
        return str_contains($value, "'")
            ? '"'.addcslashes($value, '"\\$').'"'
            : "'{$value}'";
    }
}
