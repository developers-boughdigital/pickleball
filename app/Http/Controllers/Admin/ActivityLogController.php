<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public const RANGES = ['1' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'all' => 'All time'];

    public function __invoke(Request $request): View
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'range' => array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : '30',
            'actor' => $request->string('actor')->value(),
            'action' => $request->string('action')->value(),
            'result' => $request->string('result')->value(),
        ];

        $events = ActivityLog::query()
            ->when($filters['range'] !== 'all', fn ($query) => $query->where('created_at', '>=', $filters['range'] === '1' ? today() : now()->subDays((int) $filters['range'])))
            ->when($filters['search'], fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('description', 'like', "%{$search}%")
                ->orWhere('subject_label', 'like', "%{$search}%")
                ->orWhere('actor_name', 'like', "%{$search}%")
                ->orWhere('ip_address', 'like', "%{$search}%")))
            ->when($filters['actor'], fn ($query, $actor) => $query->where('actor_name', $actor))
            ->when($filters['action'], fn ($query, $action) => $query->where('action', $action))
            ->when($filters['result'] === 'failed', fn ($query) => $query->where('succeeded', false))
            ->when($filters['result'] === 'success', fn ($query) => $query->where('succeeded', true))
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $selected = ActivityLog::find($request->integer('event')) ?? $events->first();

        $actors = ActivityLog::whereNotNull('actor_name')->distinct()->orderBy('actor_name')->pluck('actor_name');
        $actions = ActivityLog::distinct()->orderBy('action')->pluck('action');

        return view('admin.activity', [
            'events' => $events,
            'selected' => $selected,
            'filters' => $filters,
            'ranges' => self::RANGES,
            'actors' => $actors->combine($actors)->all(),
            'actions' => $actions->mapWithKeys(fn ($action) => [$action => (new ActivityLog(['action' => $action]))->actionLabel()])->all(),
            'results' => ['success' => 'Succeeded', 'failed' => 'Failed'],
            'stats' => [
                'today' => ActivityLog::where('created_at', '>=', today())->count(),
                'failedToday' => ActivityLog::where('created_at', '>=', today())->where('succeeded', false)->count(),
                'signInsToday' => ActivityLog::where('created_at', '>=', today())->where('action', 'auth.login')->count(),
                'total' => ActivityLog::count(),
            ],
        ]);
    }
}
