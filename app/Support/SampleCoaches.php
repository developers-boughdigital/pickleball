<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Placeholder coaches for the public coach index until coaches have their own
 * table. Swap all() for a query when they do; the views only read these keys.
 */
class SampleCoaches
{
    public const ACCREDITATIONS = [
        'pa1' => 'Pickleball Australia Level 1',
        'pa2' => 'Pickleball Australia Level 2',
        'international' => 'International accreditation',
    ];

    public const LESSON_TYPES = [
        'private' => 'Private (1 on 1)',
        'semi_private' => 'Semi-private (2 to 4)',
        'clinic' => 'Group clinic',
        'junior' => 'Junior program',
        'school' => 'School program',
        'corporate' => 'Corporate',
    ];

    public const FOCUS = [
        'getting_started' => 'Getting started',
        'third_shot_drop' => 'Third shot drop',
        'dinking' => 'Dinking',
        'serve_return' => 'Serve and return',
        'doubles' => 'Doubles strategy',
        'fitness' => 'Fitness',
    ];

    public const TIMES = [
        'mornings' => 'Mornings',
        'afternoons' => 'Afternoons',
        'evenings' => 'Evenings',
        'weekends' => 'Weekends',
    ];

    public const PRICES = [
        'under_60' => ['Under $60', 0, 5999],
        '60_90' => ['$60 to $90', 6000, 9000],
        'over_90' => ['Over $90', 9001, PHP_INT_MAX],
    ];

    public static function all(): Collection
    {
        return collect([
            ['slug' => 'sam-m', 'name' => 'Sam M.', 'suburb' => 'Eltham', 'state' => 'VIC', 'lat' => -37.7140, 'lng' => 145.1480, 'accreditation' => 'pa2', 'wwcc' => true, 'levels' => ['beginner', 'intermediate', 'advanced'], 'types' => ['private', 'semi_private', 'clinic'], 'focus' => ['third_shot_drop', 'doubles', 'dinking'], 'times' => ['evenings', 'weekends'], 'price_cents' => 7000, 'unit' => 'hr', 'replies' => 'Usually replies within a day', 'bio' => 'Former state doubles player. Focus on third shot drops, resets and moving as a pair.'],
            ['slug' => 'mei-w', 'name' => 'Mei W.', 'suburb' => 'Reservoir', 'state' => 'VIC', 'lat' => -37.7170, 'lng' => 145.0070, 'accreditation' => 'pa1', 'wwcc' => true, 'levels' => ['new', 'beginner', 'intermediate'], 'types' => ['private', 'clinic', 'junior'], 'focus' => ['getting_started', 'serve_return', 'third_shot_drop'], 'times' => ['evenings'], 'price_cents' => 5500, 'unit' => 'hr', 'replies' => 'Usually replies within a few hours', 'bio' => 'Patient, step by step lessons for players moving up from social play. Runs junior holiday clinics.'],
            ['slug' => 'dev-r', 'name' => 'Dev R.', 'suburb' => 'Bundoora', 'state' => 'VIC', 'lat' => -37.6980, 'lng' => 145.0600, 'accreditation' => 'international', 'wwcc' => false, 'levels' => ['intermediate', 'advanced', 'open'], 'types' => ['private', 'semi_private'], 'focus' => ['third_shot_drop', 'doubles'], 'times' => ['evenings'], 'price_cents' => 9500, 'unit' => 'hr', 'replies' => 'Usually replies within 2 days', 'bio' => 'Tournament preparation and video review. Best for players chasing a higher DUPR rating.'],
            ['slug' => 'ann-b', 'name' => 'Ann B.', 'suburb' => 'Doncaster East', 'state' => 'VIC', 'lat' => -37.7860, 'lng' => 145.1590, 'accreditation' => 'pa1', 'wwcc' => false, 'levels' => ['beginner', 'intermediate'], 'types' => ['clinic'], 'focus' => ['getting_started', 'dinking'], 'times' => ['mornings'], 'price_cents' => 2500, 'unit' => 'person', 'replies' => 'New coach', 'bio' => 'Group clinics for over-50s. Small groups, plenty of rallying.'],
            ['slug' => 'priya-k', 'name' => 'Priya K.', 'suburb' => 'Brunswick', 'state' => 'VIC', 'lat' => -37.7670, 'lng' => 144.9600, 'accreditation' => 'pa1', 'wwcc' => true, 'levels' => ['new', 'beginner'], 'types' => ['private', 'clinic', 'school'], 'focus' => ['getting_started', 'serve_return'], 'times' => ['afternoons', 'weekends'], 'price_cents' => 5500, 'unit' => 'hr', 'replies' => 'Usually replies within a day', 'bio' => 'Friendly first lessons for complete beginners, from the grip to your first rally.'],
            ['slug' => 'tom-l', 'name' => 'Tom L.', 'suburb' => 'Bayswater', 'state' => 'VIC', 'lat' => -37.8420, 'lng' => 145.2680, 'accreditation' => 'pa2', 'wwcc' => true, 'levels' => ['advanced', 'open'], 'types' => ['private', 'semi_private', 'corporate'], 'focus' => ['doubles', 'fitness', 'third_shot_drop'], 'times' => ['mornings', 'evenings'], 'price_cents' => 9000, 'unit' => 'hr', 'replies' => 'Usually replies within a day', 'bio' => 'High-intensity drills and match play for competitive players. Corporate team sessions on request.'],
            ['slug' => 'grace-h', 'name' => 'Grace H.', 'suburb' => 'Albert Park', 'state' => 'VIC', 'lat' => -37.8410, 'lng' => 144.9550, 'accreditation' => 'pa2', 'wwcc' => true, 'levels' => ['beginner', 'intermediate'], 'types' => ['private', 'semi_private', 'junior'], 'focus' => ['dinking', 'serve_return', 'doubles'], 'times' => ['afternoons', 'weekends'], 'price_cents' => 6500, 'unit' => 'hr', 'replies' => 'Usually replies within a few hours', 'bio' => 'Soft game specialist. Builds consistency at the kitchen line before adding power.'],
            ['slug' => 'luke-p', 'name' => 'Luke P.', 'suburb' => 'Clayton', 'state' => 'VIC', 'lat' => -37.9250, 'lng' => 145.1200, 'accreditation' => 'pa1', 'wwcc' => false, 'levels' => ['new', 'beginner', 'intermediate'], 'types' => ['clinic', 'corporate'], 'focus' => ['getting_started', 'fitness'], 'times' => ['evenings', 'weekends'], 'price_cents' => 3000, 'unit' => 'person', 'replies' => 'Usually replies within a day', 'bio' => 'Fun, fast-paced group clinics. Great for workmates and friends trying the game together.'],
            ['slug' => 'nina-t', 'name' => 'Nina T.', 'suburb' => 'Thornbury', 'state' => 'VIC', 'lat' => -37.7560, 'lng' => 145.0050, 'accreditation' => 'international', 'wwcc' => true, 'levels' => ['intermediate', 'advanced'], 'types' => ['private', 'semi_private'], 'focus' => ['third_shot_drop', 'serve_return', 'doubles'], 'times' => ['mornings', 'weekends'], 'price_cents' => 8500, 'unit' => 'hr', 'replies' => 'Usually replies within a day', 'bio' => 'Shot technique and strategy, with video breakdowns after each lesson.'],
        ])->map(fn (array $coach) => $coach + ['initials' => collect(explode(' ', $coach['name']))->map(fn ($part) => mb_substr($part, 0, 1))->implode('')]);
    }
}
