<?php

namespace App\Http\Controllers;

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

            // Last 30 days, labelled every ~6 days and ending today.
            'chartLabels' => collect([29, 23, 17, 11, 5, 0])->map(fn (int $days) => now()->subDays($days)->format('j M')),

            // Sample data until clubs, sessions and bookings are in the database.
            'stats' => [
                ['label' => 'Sessions this week', 'value' => '312', 'note' => '+8.4% vs last week', 'icon' => 'calendar', 'tone' => 'green', 'spark' => '0,22 12,20 22,23 34,14 46,17 58,9 70,13 82,8 100,10'],
                ['label' => 'Active players', 'value' => '4,218', 'note' => '+6.4% vs last month', 'icon' => 'users', 'tone' => 'green', 'spark' => '0,20 14,22 26,12 38,16 50,9 62,13 76,6 88,8 100,7'],
                ['label' => 'Listing claims', 'value' => '23', 'note' => 'Waiting for review', 'icon' => 'shield-check', 'tone' => 'gold', 'spark' => '0,22 14,21 26,22 38,12 50,16 62,9 74,14 86,6 100,10'],
                ['label' => 'Sessions with no host', 'value' => '4', 'note' => 'Before Saturday', 'icon' => 'triangle-alert', 'tone' => 'red', 'spark' => '0,8 14,5 26,10 38,4 50,12 62,6 74,14 86,8 100,15'],
            ],
            'upcomingSessions' => [
                ['title' => 'Tuesday Club Night', 'club' => 'Banyule Pickleball Club', 'when' => 'Tue 7:00 PM', 'venue' => 'Greensborough Leisure Centre', 'level' => 'Beginner – Intermediate', 'spots' => '6 spots left'],
                ['title' => 'Come and Try', 'club' => 'Darebin Pickleball Club', 'when' => 'Wed 10:00 AM', 'venue' => 'Narrandjeri Stadium, Thornbury', 'level' => 'New', 'spots' => '14 spots left'],
                ['title' => 'Social Play', 'club' => 'Bayside Pickleball Club', 'when' => 'Thu 6:30 PM', 'venue' => 'Brighton Recreation Centre', 'level' => 'All levels', 'spots' => 'Waitlist'],
                ['title' => 'Advanced Round Robin', 'club' => 'Geelong Pickleball Club', 'when' => 'Sat 9:00 AM', 'venue' => 'Leisurelink, Waurn Ponds', 'level' => 'Advanced – Open', 'spots' => '3 spots left'],
            ],
            'claims' => [
                ['listing' => 'Darebin Pickleball Club', 'type' => 'Club', 'claimant' => 'Sarah Nguyen', 'submitted' => '3 days ago', 'status' => 'Pending review'],
                ['listing' => 'Knox Indoor Pickleball', 'type' => 'Venue', 'claimant' => 'Helen Brooks', 'submitted' => '2 days ago', 'status' => 'Pending review'],
                ['listing' => 'Moonee Valley Pickleballers', 'type' => 'Club', 'claimant' => 'Mark Rossi', 'submitted' => 'Yesterday', 'status' => 'Evidence needed'],
            ],
            'activity' => [
                ['initials' => 'TN', 'tone' => 'gold', 'text' => 'Tom Nguyen published Tuesday Club Night at Banyule Pickleball Club', 'time' => '8 min ago'],
                ['initials' => 'PS', 'tone' => 'gray', 'text' => 'Priya Sharma confirmed Liam O’Connor as Intermediate', 'time' => '42 min ago'],
                ['initials' => 'MC', 'tone' => 'gray', 'text' => 'Megan Clarke approved the claim for Bayside Pickleball Club', 'time' => '1 hr ago'],
                ['initials' => null, 'tone' => 'gray', 'text' => 'System generated 90 days of session occurrences', 'time' => '2 hrs ago'],
            ],
        ]);
    }
}
