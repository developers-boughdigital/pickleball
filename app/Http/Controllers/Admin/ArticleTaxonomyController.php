<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ArticleCategory;
use App\Models\ArticleTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Categories (one per article) and tags (many per article, and nestable). */
class ArticleTaxonomyController extends Controller
{
    public function categories(): View
    {
        return view('admin.articles.categories', [
            'categories' => ArticleCategory::withCount('articles')->orderBy('name')->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $category = ArticleCategory::create($this->categoryData($request));
        ActivityLog::record('article_category.created', "Added the article category {$category->name}");

        return back()->with('status', 'Category added.');
    }

    public function updateCategory(Request $request, ArticleCategory $category): RedirectResponse
    {
        $category->update($this->categoryData($request, $category));

        return back()->with('status', 'Category saved.');
    }

    public function destroyCategory(ArticleCategory $category): RedirectResponse
    {
        // Its articles stay, without a category.
        $category->delete();
        ActivityLog::record('article_category.deleted', "Deleted the article category {$category->name}");

        return back()->with('status', 'Category deleted. Its articles are now uncategorised.');
    }

    public function tags(): View
    {
        return view('admin.articles.tags', ['tags' => ArticleTag::tree()]);
    }

    public function storeTag(Request $request): RedirectResponse
    {
        $tag = ArticleTag::create($this->tagData($request));
        ActivityLog::record('article_tag.created', "Added the article tag {$tag->name}");

        return back()->with('status', 'Tag added.');
    }

    public function updateTag(Request $request, ArticleTag $tag): RedirectResponse
    {
        $tag->update($this->tagData($request, $tag));

        return back()->with('status', 'Tag saved.');
    }

    public function destroyTag(ArticleTag $tag): RedirectResponse
    {
        $hadChildren = $tag->children()->exists();
        $tag->delete();
        ActivityLog::record('article_tag.deleted', "Deleted the article tag {$tag->name}");

        return back()->with('status', 'Tag deleted.'.($hadChildren ? ' The tags under it moved up a level.' : ''));
    }

    private function categoryData(Request $request, ?ArticleCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('article_categories', 'name')->ignore($category)],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:255'],
        ], ['slug.regex' => 'The address can only have lowercase letters, numbers and hyphens.']);
    }

    private function tagData(Request $request, ?ArticleTag $tag = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            // A tag can't go under itself or under one of its own children.
            'parent_id' => ['nullable', Rule::exists('article_tags', 'id'), Rule::notIn($tag?->branchIds() ?? [])],
        ], [
            'slug.regex' => 'The address can only have lowercase letters, numbers and hyphens.',
            'parent_id.not_in' => 'A tag can’t sit under itself or under one of its own sub-tags.',
        ], ['parent_id' => 'parent tag']);
    }
}
