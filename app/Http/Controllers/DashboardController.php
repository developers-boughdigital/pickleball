<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $hour = now()->hour;

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
            'recentActivity' => ActivityLog::with('user')->latest('created_at')->latest('id')->take(6)->get(),
            'newestUsers' => User::with('roles')->latest()->take(5)->get(),
            'roles' => Role::withCount('users')->orderByDesc('users_count')->orderBy('id')->get(),
        ]);
    }
}
