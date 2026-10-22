<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Sample data until roles are granted per club, venue or seller in the database.
        $users = collect([
            ['initials' => 'MC', 'tone' => 'gold', 'name' => 'Megan Clarke', 'email' => 'megan@pickleball.com.au', 'role' => 'Platform admin', 'scope' => 'All clubs and venues', 'status' => 'Active', 'last_active' => '8 min ago'],
            ['initials' => 'TN', 'tone' => 'gray', 'name' => 'Tom Nguyen', 'email' => 'tom.nguyen@banyulepickleball.org.au', 'role' => 'Club admin', 'scope' => 'Banyule Pickleball Club', 'status' => 'Active', 'last_active' => '42 min ago'],
            ['initials' => 'PS', 'tone' => 'gray', 'name' => 'Priya Sharma', 'email' => 'priya@sharmacoaching.com.au', 'role' => 'Coach', 'scope' => 'PA Level 2 · Darebin, Banyule', 'status' => 'Active', 'last_active' => '1 hr ago'],
            ['initials' => 'DW', 'tone' => 'gray', 'name' => 'Daniel Wright', 'email' => 'dan.wright@gmail.com', 'role' => 'Club session host', 'scope' => 'Banyule Pickleball Club', 'status' => 'Active', 'last_active' => 'Yesterday'],
            ['initials' => 'HB', 'tone' => 'gray', 'name' => 'Helen Brooks', 'email' => 'helen.brooks@knox.vic.gov.au', 'role' => 'Venue manager', 'scope' => 'Knox Indoor Pickleball', 'status' => 'Pending', 'last_active' => '2 days ago'],
            ['initials' => 'LO', 'tone' => 'gray', 'name' => 'Liam O’Connor', 'email' => 'liam.oconnor@outlook.com', 'role' => 'Player', 'scope' => 'Intermediate · confirmed by coach', 'status' => 'Active', 'last_active' => '3 hrs ago'],
            ['initials' => 'MR', 'tone' => 'gray', 'name' => 'Mark Rossi', 'email' => 'mark.rossi@bigpond.com', 'role' => 'Player', 'scope' => 'Unrated', 'status' => 'Suspended', 'last_active' => '3 weeks ago'],
        ]);

        $filters = $request->only('search', 'role', 'status');

        $filtered = $users
            ->when($filters['search'] ?? null, fn ($c, $search) => $c->filter(
                fn ($u) => Str::contains($u['name'].' '.$u['email'].' '.$u['scope'], $search, ignoreCase: true)
            ))
            ->when($filters['role'] ?? null, fn ($c, $role) => $c->where('role', $role))
            ->when($filters['status'] ?? null, fn ($c, $status) => $c->where('status', $status));

        return view('admin.users', [
            'users' => $filtered,
            'filters' => $filters,
            'roles' => $users->pluck('role', 'role')->all(),
            'statuses' => ['Active' => 'Active', 'Pending' => 'Pending', 'Suspended' => 'Suspended'],
        ]);
    }
}
