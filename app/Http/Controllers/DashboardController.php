<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\ListingClaim;
use App\Models\ListingReport;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use App\Support\Geography;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        // Players and club people have their own page; the overview is for staff.
        if (! $request->user()->isStaff()) {
            return redirect()->route('account');
        }

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
            // Step 14: who's asking to run a club or venue, and how many are run by their owners.
            'claims' => ! $pending && $request->user()->can('claims.view') ? [
                'waiting' => ListingClaim::pending()->count(),
                'oldest' => ListingClaim::pending()->with(['claimable', 'user'])->oldest()->take(3)->get()->filter(fn ($claim) => $claim->claimable),
                'claimedClubs' => Club::where('listing_status', 'claimed')->count(),
                'claimedVenues' => Venue::where('listing_status', 'claimed')->count(),
                'claimable' => Club::where('status', 'published')->whereIn('listing_status', ['unclaimed', 'pending_claim'])->count(),
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
