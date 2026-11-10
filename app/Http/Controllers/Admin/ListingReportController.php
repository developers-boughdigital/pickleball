<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ListingReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Inbox for "tell us about a court" and "report incorrect details". */
class ListingReportController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status') === 'resolved' ? 'resolved' : 'open';

        return view('admin.reports.index', [
            'reports' => ListingReport::query()->with(['reportable', 'resolver'])
                ->where('status', $status)
                ->latest()->paginate(25)->withQueryString(),
            'status' => $status,
            'counts' => ListingReport::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function update(Request $request, ListingReport $report): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(['open', 'resolved'])]])['status'];

        $report->update([
            'status' => $status,
            'resolved_by' => $status === 'resolved' ? $request->user()->id : null,
            'resolved_at' => $status === 'resolved' ? now() : null,
        ]);

        $what = ListingReport::KINDS[$report->kind] ?? 'Report';
        ActivityLog::record(
            $status === 'resolved' ? 'report.resolved' : 'report.reopened',
            ($status === 'resolved' ? 'Resolved' : 'Reopened')." a “{$what}” report".($report->area ? " for {$report->area}" : ''),
        );

        return back()->with('status', $status === 'resolved' ? 'Marked as resolved.' : 'Reopened.');
    }
}
