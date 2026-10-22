<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Sample data until actions on other people's data are written to audit_log.
        // IPs use the documentation ranges (RFC 5737) so they can't belong to anyone.
        $events = collect([
            ['id' => 1, 'time' => 'Today 7:42 PM', 'timestamp' => 'Today, 7:42 PM', 'actor' => 'Megan Clarke', 'action' => 'Approved listing claim', 'module' => 'Claims', 'target' => 'Bayside Pickleball Club', 'ip' => '203.0.113.18', 'result' => 'Success', 'summary' => 'Approved Sarah Nguyen as club admin for Bayside Pickleball Club, verified by committee email'],
            ['id' => 2, 'time' => 'Today 6:15 PM', 'timestamp' => 'Today, 6:15 PM', 'actor' => 'Tom Nguyen', 'action' => 'Published session', 'module' => 'Sessions', 'target' => 'Tuesday Club Night', 'ip' => '198.51.100.92', 'result' => 'Success', 'summary' => 'Published Tuesday Club Night at Greensborough Leisure Centre, weekly, Beginner to Intermediate, 24 places'],
            ['id' => 3, 'time' => 'Today 4:56 PM', 'timestamp' => 'Today, 4:56 PM', 'actor' => 'Priya Sharma', 'action' => 'Set verified level', 'module' => 'Levels', 'target' => 'Liam O’Connor', 'ip' => '198.51.100.77', 'result' => 'Success', 'summary' => 'Changed level from Beginner (quiz) to Intermediate (coach assessment)'],
            ['id' => 4, 'time' => 'Yesterday 9:14 PM', 'timestamp' => 'Yesterday, 9:14 PM', 'actor' => 'Daniel Wright', 'action' => 'Failed sign-in', 'module' => 'User & Security', 'target' => 'Daniel Wright', 'ip' => '203.0.113.11', 'result' => 'Failed', 'summary' => 'Magic link expired before it was used (third attempt this hour)'],
            ['id' => 5, 'time' => 'Yesterday 3:30 PM', 'timestamp' => 'Yesterday, 3:30 PM', 'actor' => 'Megan Clarke', 'action' => 'Refunded casual fee', 'module' => 'Payments', 'target' => 'Booking BK-20418', 'ip' => '203.0.113.18', 'result' => 'Success', 'summary' => 'Refunded $12.00 to Mark Rossi after Darebin Pickleball Club cancelled Wednesday Social Play'],
            ['id' => 6, 'time' => '20 Oct 11:09 AM', 'timestamp' => '20 Oct, 11:09 AM', 'actor' => 'Megan Clarke', 'action' => 'Merged duplicate venue', 'module' => 'Directory', 'target' => 'Greensborough Leisure Centre', 'ip' => '203.0.113.18', 'result' => 'Success', 'summary' => 'Merged two imported records for Greensborough Leisure Centre and kept /venues/greensborough-leisure-centre'],
            ['id' => 7, 'time' => '19 Oct 2:40 PM', 'timestamp' => '19 Oct, 2:40 PM', 'actor' => 'Megan Clarke', 'action' => 'Impersonated user', 'module' => 'User & Security', 'target' => 'Helen Brooks', 'ip' => '203.0.113.18', 'result' => 'Success', 'summary' => 'Signed in as Helen Brooks for 6 minutes to help update Knox Indoor Pickleball opening hours'],
        ]);

        $filters = $request->only('search', 'range', 'actor', 'module', 'action');

        $filtered = $events
            ->when($filters['search'] ?? null, fn ($c, $search) => $c->filter(
                fn ($e) => Str::contains("{$e['actor']} {$e['action']} {$e['target']} {$e['module']}", $search, ignoreCase: true)
            ))
            ->when($filters['actor'] ?? null, fn ($c, $actor) => $c->where('actor', $actor))
            ->when($filters['module'] ?? null, fn ($c, $module) => $c->where('module', $module))
            ->when($filters['action'] ?? null, fn ($c, $action) => $c->where('action', $action));

        $options = fn (string $key) => $events->pluck($key, $key)->sort()->all();

        return view('admin.activity', [
            'events' => $filtered,
            'selected' => $events->firstWhere('id', (int) $request->query('event', 1)) ?? $events->first(),
            'filters' => $filters,
            'ranges' => ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'],
            'actors' => $options('actor'),
            'modules' => $options('module'),
            'actions' => $options('action'),
            'isFiltered' => array_filter(Arr::except($filters, 'range')) !== [],
        ]);
    }
}
