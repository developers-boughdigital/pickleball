<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    public const ABILITIES = ['view', 'create', 'edit', 'approve'];

    public function __invoke(Request $request): View
    {
        $all = self::ABILITIES;
        $none = [];

        // Sample data until roles live in the database. Roles follow the platform brief:
        // one account type, with roles granted per club, venue or seller.
        $roles = collect([
            ['slug' => 'platform-admin', 'name' => 'Platform admin', 'type' => 'platform', 'description' => 'Approve claims, moderate content, manage listings, refunds and support', 'users' => 3, 'scope' => 'Whole platform', 'updated' => 'Today', 'matrix' => [
                'Directory' => $all, 'Clubs' => $all, 'Sessions & bookings' => $all, 'Club Night' => $all, 'Members' => $all, 'Payments' => $all, 'Levels' => $all, 'Shop' => $all,
            ]],
            ['slug' => 'club-admin', 'name' => 'Club admin', 'type' => 'club', 'description' => 'Club profile, sessions, events, members and payments; runs Club Night', 'users' => 152, 'scope' => 'Their club', 'updated' => '2 hours ago', 'matrix' => [
                'Directory' => ['view'], 'Clubs' => ['view', 'create', 'edit'], 'Sessions & bookings' => $all, 'Club Night' => ['view', 'create', 'edit'], 'Members' => $all, 'Payments' => ['view', 'edit'], 'Levels' => ['view', 'edit', 'approve'], 'Shop' => ['view'],
            ]],
            ['slug' => 'session-host', 'name' => 'Club session host', 'type' => 'club', 'description' => 'Check-in, court rotation and scores on the night. No money or member data', 'users' => 34, 'scope' => 'Their club’s sessions', 'updated' => 'Yesterday', 'matrix' => [
                'Directory' => ['view'], 'Clubs' => ['view'], 'Sessions & bookings' => ['view'], 'Club Night' => ['view', 'create', 'edit'], 'Members' => $none, 'Payments' => $none, 'Levels' => $none, 'Shop' => ['view'],
            ]],
            ['slug' => 'coach', 'name' => 'Coach', 'type' => 'platform', 'description' => 'Coach profile, lessons and clinics, enquiries, assess player levels', 'users' => 42, 'scope' => 'Own profile and students', 'updated' => '3 days ago', 'matrix' => [
                'Directory' => ['view'], 'Clubs' => ['view'], 'Sessions & bookings' => ['view', 'create', 'edit'], 'Club Night' => $none, 'Members' => $none, 'Payments' => $none, 'Levels' => ['view', 'edit', 'approve'], 'Shop' => ['view'],
            ]],
            ['slug' => 'venue-manager', 'name' => 'Venue manager', 'type' => 'venue', 'description' => 'Venue listing, courts, opening hours and prices', 'users' => 18, 'scope' => 'Their venue', 'updated' => '5 days ago', 'matrix' => [
                'Directory' => ['view', 'edit'], 'Clubs' => ['view'], 'Sessions & bookings' => ['view'], 'Club Night' => $none, 'Members' => $none, 'Payments' => $none, 'Levels' => $none, 'Shop' => ['view'],
            ]],
            ['slug' => 'seller', 'name' => 'Seller', 'type' => 'platform', 'description' => 'Product listings in the shop (Phase 2)', 'users' => 0, 'scope' => 'Own products', 'updated' => '2 weeks ago', 'matrix' => [
                'Directory' => ['view'], 'Clubs' => $none, 'Sessions & bookings' => $none, 'Club Night' => $none, 'Members' => $none, 'Payments' => $none, 'Levels' => $none, 'Shop' => ['view', 'create', 'edit'],
            ]],
            ['slug' => 'player', 'name' => 'Player', 'type' => 'platform', 'description' => 'Profile and level, join sessions, pay, follow clubs', 'users' => 4218, 'scope' => 'Own account', 'updated' => '2 weeks ago', 'matrix' => [
                'Directory' => ['view'], 'Clubs' => ['view'], 'Sessions & bookings' => ['view', 'create'], 'Club Night' => ['view', 'edit'], 'Members' => $none, 'Payments' => ['view'], 'Levels' => ['view', 'create'], 'Shop' => ['view'],
            ]],
        ]);

        $filters = $request->only('search', 'type');

        $filtered = $roles
            ->when($filters['search'] ?? null, fn ($c, $search) => $c->filter(
                fn ($r) => Str::contains($r['name'].' '.$r['description'], $search, ignoreCase: true)
            ))
            ->when($filters['type'] ?? null, fn ($c, $type) => $c->where('type', $type));

        return view('admin.roles', [
            'roles' => $filtered,
            'selected' => $roles->firstWhere('slug', $request->query('role')) ?? $roles->firstWhere('slug', 'club-admin'),
            'filters' => $filters,
            'types' => ['platform' => 'Platform-wide', 'club' => 'Granted per club', 'venue' => 'Granted per venue'],
            'abilities' => self::ABILITIES,
        ]);
    }
}
