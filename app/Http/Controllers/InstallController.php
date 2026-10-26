<?php

namespace App\Http\Controllers;

use App\Support\Installer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * These routes run outside the "web" middleware group: before install there
 * is no app key, session store or database for that stack to use.
 */
class InstallController extends Controller
{
    public function __construct(private Installer $installer) {}

    public function show(Request $request): Response|View
    {
        $this->ensureNotInstalled();

        // A fresh clone has no public_html/build (it's git-ignored); explain rather than 500.
        if (! $this->installer->assetsBuilt()) {
            return response()->view('install.assets-missing', status: 503);
        }

        return view('install.index', [
            'requirements' => $this->installer->requirements(),
            'meetsRequirements' => $this->installer->meetsRequirements(),
            'drivers' => $this->installer->drivers(),
            'defaults' => [
                'appName' => 'PickleBall',
                'appUrl' => $request->root(),
                'sqlitePath' => $this->installer->defaultSqlitePath(),
            ],
        ]);
    }

    public function testDatabase(Request $request): JsonResponse
    {
        $this->ensureNotInstalled();

        $data = $request->validate($this->databaseRules());

        try {
            $this->installer->testConnection($data);
        } catch (Throwable $e) {
            return $this->connectionFailed($e);
        }

        return response()->json(['message' => 'Connected to the database.']);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureNotInstalled();

        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:60'],
            'app_url' => ['required', 'url:http,https'],
            'app_env' => ['required', Rule::in(['production', 'local'])],
            ...$this->databaseRules(),
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! $this->installer->meetsRequirements()) {
            return response()->json(['message' => 'This server does not meet the requirements yet.'], 422);
        }

        try {
            $this->installer->testConnection($data);
        } catch (Throwable $e) {
            return $this->connectionFailed($e);
        }

        try {
            $this->installer->install($data);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Installation failed: '.$e->getMessage()], 500);
        }

        return response()->json(['message' => 'Installed.', 'login' => url('/login')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseRules(): array
    {
        $available = collect($this->installer->drivers())->filter(fn ($d) => $d['available'])->keys()->all();

        return [
            'db_driver' => ['required', Rule::in($available)],
            'db_host' => ['exclude_if:db_driver,sqlite', 'required', 'string', 'max:255'],
            'db_port' => ['exclude_if:db_driver,sqlite', 'required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['exclude_if:db_driver,sqlite', 'required', 'string', 'max:255'],
            'db_password' => ['exclude_if:db_driver,sqlite', 'nullable', 'string', 'max:255'],
        ];
    }

    private function connectionFailed(Throwable $e): JsonResponse
    {
        $message = 'Could not connect to the database: '.$e->getMessage();

        return response()->json(['message' => $message, 'errors' => ['db_connection' => [$message]]], 422);
    }

    private function ensureNotInstalled(): void
    {
        abort_if($this->installer->installed(), 404);
    }
}
