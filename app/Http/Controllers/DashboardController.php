<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\ListingReport;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use App\Support\Geography;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $hour = now()->hour;

        // After a deploy the code can be ahead of the database. Don't touch the new
        // tables until the migrations have run (Admin › Database), just say so.
        $pending = $this->pendingMigrations();

        return view('admin.dashboard', [
            'greeting' => match (true) {
                $hour < 12 => 'Good morning',
                $hour < 18 => 'Good afternoon',
                default => 'Good evening',
            },
            'firstName' => Str::before($request->user()->name, ' '),
            'stats' => [
                'users' => User::count(),
                'newUsers' => User::where('created_at', '>=', now()->startOfMonth())->count(),
                'roles' => Role::count(),
                'permissions' => Permission::count(),
                'modules' => Permission::modules()->count(),
                'eventsToday' => ActivityLog::where('created_at', '>=', today())->count(),
                'failedToday' => ActivityLog::where('created_at', '>=', today())->where('succeeded', false)->count(),
            ],
            // The directory at this stage: what's live, and whether location pages have what they need.
            'pendingMigrations' => $pending,
            'directory' => ! $pending && $request->user()->canAny(['venues.view', 'clubs.view']) ? [
                'venues' => Venue::count(),
                'venuesLive' => Venue::where('status', 'published')->count(),
                'clubs' => Club::count(),
                'clubsLive' => Club::where('status', 'published')->count(),
                'locations' => Geography::coverage()['venues'],
                'openReports' => ListingReport::where('status', 'open')->count(),
            ] : null,
            'recentActivity' => ActivityLog::with('user')->latest('created_at')->latest('id')->take(6)->get(),
            'newestUsers' => User::with('roles')->latest()->take(5)->get(),
            'roles' => Role::withCount('users')->orderByDesc('users_count')->orderBy('id')->get(),
        ]);
    }

    private function pendingMigrations(): int
    {
        $migrator = app('migrator');
        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];

        return count(array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $ran));
    }
}
