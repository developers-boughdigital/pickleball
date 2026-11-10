<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Publish, hide or return to draft one listing, the ticked rows, or every row
 * matching the list's filters. Only checked listings can be published
 * (files/IMPORT_GUIDE.md, rule 3); others are skipped and counted.
 */
trait ChangesListingStatus
{
    /** @param Builder $matching the list's query with its current filters */
    protected function changeStatus(Request $request, Builder $matching, string $noun): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['published', 'hidden', 'draft'])],
            'ids' => ['required_without:all', 'array', 'max:1000'],
            'ids.*' => ['string'],
            'all' => ['nullable', 'boolean'],
        ], ['ids.required_without' => "Tick at least one {$noun} first."]);

        $listings = $request->boolean('all') ? $matching : $matching->getModel()->newQuery()->whereKey($data['ids']);
        $status = $data['status'];

        $changed = 0;
        $notChecked = 0;
        $listings->clone()->where('status', '!=', $status)->each(function ($listing) use ($status, &$changed, &$notChecked) {
            if ($status === 'published' && $listing->record_status !== 'checked') {
                $notChecked++;

                return;
            }
            $listing->update(['status' => $status]);
            $changed++;
        });

        $verb = ['published' => 'Published', 'hidden' => 'Hid', 'draft' => 'Moved back to draft'][$status];
        $message = $changed ? "{$verb} ".$this->countOf($changed, $noun).'.' : 'Nothing changed.';
        if ($notChecked) {
            $message .= ' Skipped '.$this->countOf($notChecked, $noun).' that '.($notChecked === 1 ? 'isn’t' : 'aren’t').' checked yet. Only checked listings can go live.';
        }

        if ($changed) {
            ActivityLog::record("{$noun}.status_changed", "{$verb} ".$this->countOf($changed, $noun));
        }

        return back()->with('status', $message);
    }

    private function countOf(int $count, string $noun): string
    {
        return number_format($count).' '.Str::plural($noun, $count);
    }
}
