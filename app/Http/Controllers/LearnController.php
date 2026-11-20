<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleTag;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The public side of the article studio: /learn and each article. */
class LearnController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->query('category') ? ArticleCategory::where('slug', $request->query('category'))->first() : null;
        $tag = $request->query('tag') ? ArticleTag::where('slug', $request->query('tag'))->first() : null;

        $articles = Article::live()
            ->with(['category', 'author'])
            ->when($category, fn ($query) => $query->where('article_category_id', $category->id))
            // A parent tag shows everything filed under its sub-tags too.
            ->when($tag, fn ($query) => $query->whereHas('tags', fn ($query) => $query->whereIn('article_tags.id', $tag->branchIds())))
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('learn.index', [
            'articles' => $articles,
            'category' => $category,
            'tag' => $tag,
            'categories' => ArticleCategory::whereHas('articles', fn ($query) => $query->live())->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $article = Article::with(['category', 'author', 'tags'])->where('slug', $slug)->firstOrFail();

        // Editors can read a draft or scheduled article at its real address before it goes live.
        $preview = ! $article->isLive();
        abort_if($preview && ! $request->user()?->can('articles.view'), 404);

        return view('learn.show', [
            'article' => $article,
            'preview' => $preview,
            'related' => Article::live()->whereKeyNot($article->id)
                ->when($article->article_category_id, fn ($query, $id) => $query->where('article_category_id', $id))
                ->latest('published_at')->take(3)->get(),
        ]);
    }
}
