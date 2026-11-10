<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Search titles and descriptions for any public page, by its address. Pages
 * write their own from the data; an entry here replaces that for one page.
 * Venues and clubs also have these fields on their own edit form.
 */
class SeoController extends Controller
{
    public function index(): View
    {
        return view('admin.seo.index', ['pages' => PageMeta::orderBy('path')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['path' => PageMeta::normalise((string) $request->input('path'))]);
        $page = PageMeta::create($this->validated($request));

        ActivityLog::record('seo.created', "Set the search title and description for {$this->label($page)}");

        return back()->with('status', 'Saved. Search engines pick it up next time they visit the page.');
    }

    public function update(Request $request, PageMeta $pageMeta): RedirectResponse
    {
        $request->merge(['path' => PageMeta::normalise((string) $request->input('path'))]);
        $pageMeta->update($this->validated($request, $pageMeta));

        ActivityLog::record('seo.updated', "Changed the search title and description for {$this->label($pageMeta)}");

        return back()->with('status', 'Saved.');
    }

    public function destroy(PageMeta $pageMeta): RedirectResponse
    {
        $pageMeta->delete();

        ActivityLog::record('seo.deleted', "Removed the custom search title for {$this->label($pageMeta)}");

        return back()->with('status', 'Removed. The page writes its own title and description again.');
    }

    private function validated(Request $request, ?PageMeta $page = null): array
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:255', 'not_regex:#^(admin|dashboard|login|register|install)(/|$)#', Rule::unique('page_meta')->ignore($page)],
            'title' => ['nullable', 'string', 'max:70'],
            'description' => ['nullable', 'string', 'max:170'],
            'noindex' => ['nullable', 'boolean'],
        ], [
            'path.unique' => 'That page already has an entry. Edit it below.',
            'path.not_regex' => 'Only public pages appear in search results.',
            'title.max' => 'Keep the title to 70 characters; Google cuts off longer ones.',
            'description.max' => 'Keep the description to 170 characters; Google cuts off longer ones.',
        ]);

        return [...$data, 'noindex' => $request->boolean('noindex')];
    }

    private function label(PageMeta $page): string
    {
        return $page->path === '/' ? 'the home page' : '/'.$page->path;
    }
}
