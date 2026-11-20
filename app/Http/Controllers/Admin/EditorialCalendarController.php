<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\EditorialPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The editorial calendar: one month of planned topics, each with a
 * "Create article" button, alongside what is already scheduled or published.
 */
class EditorialCalendarController extends Controller
{
    public function index(Request $request): View
    {
        Article::publishDue();

        $month = rescue(fn () => Carbon::createFromFormat('!Y-m', (string) $request->query('month')), null, report: false) ?? now()->startOfMonth();
        $range = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];

        $plans = EditorialPlan::with(['category', 'article'])->whereBetween('planned_for', $range)->get();

        // Articles going out this month that nobody planned on the calendar still belong on it.
        $unplanned = Article::with('category')
            ->whereIn('status', ['scheduled', 'published'])
            ->whereBetween('published_at', $range)
            ->whereNotIn('id', $plans->pluck('article_id')->filter())
            ->get();

        $rows = $plans->map(fn (EditorialPlan $plan) => [
            'date' => $plan->article?->published_at && $plan->article->status !== 'draft' ? $plan->article->published_at : $plan->planned_for,
            'plan' => $plan,
            'article' => $plan->article,
            'topic' => $plan->article?->title ?? $plan->topic,
            'category' => $plan->article?->category ?? $plan->category,
        ])->concat($unplanned->map(fn (Article $article) => [
            'date' => $article->published_at,
            'plan' => null,
            'article' => $article,
            'topic' => $article->title,
            'category' => $article->category,
        ]))->sortBy('date')->values();

        return view('admin.articles.calendar', [
            'month' => $month,
            'rows' => $rows,
            'categories' => ArticleCategory::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = EditorialPlan::create([...$this->data($request), 'created_by' => $request->user()->id]);

        return redirect()->route('admin.articles.calendar', ['month' => $plan->planned_for->format('Y-m')])->with('status', 'Added to the calendar.');
    }

    public function update(Request $request, EditorialPlan $plan): RedirectResponse
    {
        $plan->update($this->data($request));

        return redirect()->route('admin.articles.calendar', ['month' => $plan->planned_for->format('Y-m')])->with('status', 'Calendar updated.');
    }

    public function destroy(EditorialPlan $plan): RedirectResponse
    {
        // Only the plan goes; an article already written from it stays in the library.
        $plan->delete();

        return back()->with('status', 'Removed from the calendar.');
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'planned_for' => ['required', 'date'],
            'topic' => ['required', 'string', 'min:3', 'max:200'],
            'article_category_id' => ['nullable', Rule::exists('article_categories', 'id')],
            'notes' => ['nullable', 'string', 'max:3000'],
        ], [], ['planned_for' => 'date', 'article_category_id' => 'category']);
    }
}
