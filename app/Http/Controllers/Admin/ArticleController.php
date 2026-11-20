<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleRevision;
use App\Models\ArticleTag;
use App\Models\EditorialPlan;
use App\Support\Articles\DraftWriter;
use App\Support\Articles\Html;
use App\Support\Articles\Shortcodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The article studio: the library, "New article with Claude", the editor where
 * a draft is reviewed and published or scheduled, and each article's history.
 */
class ArticleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:articles.view', only: ['index', 'revisions']),
            new Middleware('permission:articles.create', only: ['create', 'store']),
            new Middleware('permission:articles.update', only: ['edit', 'update', 'brief', 'write', 'restore']),
            new Middleware('permission:articles.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        Article::publishDue();

        $filters = [
            'status' => array_key_exists((string) $request->query('status'), Article::STATUSES) ? $request->query('status') : null,
            'search' => trim((string) $request->query('search')) ?: null,
            'category' => $request->integer('category') ?: null,
        ];

        $articles = Article::query()
            ->with(['category', 'author', 'tags'])
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['category'], fn ($query, $id) => $query->where('article_category_id', $id))
            ->when($filters['search'], fn ($query, $search) => $query->where(fn ($query) => $query
                ->whereRaw('lower(title) like ?', ['%'.Str::lower($search).'%'])
                ->orWhereRaw('lower(slug) like ?', ['%'.Str::lower($search).'%'])))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.articles.index', [
            'articles' => $articles,
            'filters' => $filters,
            'counts' => Article::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'categories' => ArticleCategory::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** "New article with Claude": a topic, up to three sources and instructions. */
    public function create(Request $request): View
    {
        $plan = $request->integer('plan') ? EditorialPlan::whereNull('article_id')->find($request->integer('plan')) : null;

        return view('admin.articles.create', [
            'plan' => $plan,
            'categories' => ArticleCategory::orderBy('name')->get(),
            'claudeReady' => filled(config('services.anthropic.key')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $byHand = $request->input('mode') === 'blank';
        $data = $request->validate([
            ...$this->briefRules(),
            'article_category_id' => ['nullable', Rule::exists('article_categories', 'id')],
            'plan_id' => ['nullable', Rule::exists('editorial_plans', 'id')->whereNull('article_id')],
        ], [], $this->briefLabels());

        $article = Article::create([
            'title' => $data['topic'],
            'status' => 'draft',
            'author_id' => $request->user()->id,
            'article_category_id' => $data['article_category_id'] ?? null,
            'brief' => $byHand ? null : $this->briefFrom($data),
            'writing_status' => $byHand ? null : 'pending',
        ]);

        if ($data['plan_id'] ?? null) {
            EditorialPlan::whereKey($data['plan_id'])->update(['article_id' => $article->id]);
        }

        ActivityLog::record('article.created', ($byHand ? 'Started' : 'Asked Claude to draft')." the article “{$article->title}”", $article, $article->title);

        // The editor page starts Claude writing as soon as it opens.
        return redirect()->route('admin.articles.edit', $article);
    }

    public function edit(Article $article): View
    {
        if ($article->writingStalled()) {
            $article->update(['writing_status' => 'failed', 'writing_error' => 'The draft stopped part-way, most likely because the server cut the request off. Try again.']);
        }

        return view('admin.articles.edit', [
            'article' => $article->load(['tags', 'category', 'author']),
            'categories' => ArticleCategory::orderBy('name')->get(),
            'tags' => ArticleTag::tree(),
            'shortcodes' => Shortcodes::HELP,
            'revisionCount' => $article->revisions()->count(),
            'claudeReady' => filled(config('services.anthropic.key')),
        ]);
    }

    /**
     * Have Claude write the draft, sending progress to the browser line by line
     * (one JSON object per line) so the editor watches it being written.
     */
    public function write(Request $request, Article $article, DraftWriter $writer): StreamedResponse|JsonResponse
    {
        // Claim the job in one query, so a second tab or a double click can't start a second draft.
        $claimed = Article::whereKey($article->id)
            ->where(fn ($query) => $query->whereIn('writing_status', ['pending', 'failed'])
                ->orWhere(fn ($query) => $query->where('writing_status', 'writing')->where('writing_started_at', '<', now()->subMinutes(12))))
            ->update(['writing_status' => 'writing', 'writing_started_at' => now()]);

        if (! $claimed) {
            return response()->json(['message' => 'This draft is already being written, or is finished.'], 409);
        }

        $user = $request->user();

        return response()->stream(function () use ($writer, $article, $user) {
            // Keep writing if the tab is closed; the finished draft is waiting in the library.
            ignore_user_abort(true);
            set_time_limit(0);

            $send = function (string $type, string $text) {
                echo json_encode(['type' => $type, 'text' => $text])."\n";
                ob_get_level() > 0 && @ob_flush();
                flush();
            };

            if ($writer->write($article->fresh(), $user, $send)) {
                $send('done', route('admin.articles.edit', $article));
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache, no-transform',
            // Tells nginx not to hold the lines back until the end.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** Change what Claude is asked for and have it write (or rewrite) the draft. */
    public function brief(Request $request, Article $article): RedirectResponse
    {
        if ($article->writing_status === 'writing' && ! $article->writingStalled()) {
            return back()->with('status', 'Claude is still writing this one.');
        }

        $data = $request->validate($this->briefRules(), [], $this->briefLabels());

        // A rewrite replaces the words, so keep what's there now where it can be brought back.
        filled($article->body) && $article->keepRevision('Before Claude rewrote it', $request->user());

        $article->update(['brief' => $this->briefFrom($data), 'writing_status' => 'pending', 'writing_error' => null]);

        return redirect()->route('admin.articles.edit', $article);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:400000'],
            'article_category_id' => ['nullable', Rule::exists('article_categories', 'id')],
            'tags' => ['array'],
            'tags.*' => [Rule::exists('article_tags', 'id')],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            // No SVG: it can carry scripts and is served from our own domain.
            'featured_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:3072'],
            'remove_image' => ['boolean'],
            'intent' => ['required', Rule::in(['save', 'publish', 'schedule', 'unpublish'])],
            'publish_at' => ['nullable', 'required_if:intent,schedule', 'date', 'after:now'],
        ], [
            'slug.regex' => 'The address can only have lowercase letters, numbers and hyphens.',
            'publish_at.after' => 'Pick a time in the future, or use Publish now.',
            'publish_at.required_if' => 'Pick the date and time to publish.',
        ], ['publish_at' => 'publish time', 'article_category_id' => 'category']);

        $action = $data['intent'];
        $data['body'] = Html::clean($data['body'] ?? '');

        if ($action !== 'save') {
            abort_unless($request->user()->can('articles.publish'), 403);
        }
        if (in_array($action, ['publish', 'schedule']) && blank(strip_tags($data['body']))) {
            throw ValidationException::withMessages(['body' => 'Write the article before publishing it.']);
        }

        $article->fill(collect($data)->only([...Article::CONTENT, 'article_category_id'])->all());
        filled($data['slug'] ?? null) && $article->slug = $data['slug'];

        if ($request->hasFile('featured_image')) {
            $article->featured_image = $this->storeImage($request->file('featured_image'), $article->featured_image);
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($article->featured_image);
            $article->featured_image = null;
        }

        [$note, $message] = match ($action) {
            'publish' => ['Published', 'Published. It’s live on the site.'],
            'schedule' => ['Scheduled', 'Scheduled.'],
            'unpublish' => ['Unpublished', 'Taken off the site and back to draft.'],
            default => [$article->isLive() ? 'Saved while live' : 'Saved', $article->isLive() ? 'Saved. The changes are live.' : 'Draft saved.'],
        };

        match ($action) {
            // Republishing a live article keeps its original date.
            'publish' => $article->fill(['status' => 'published', 'published_at' => $article->isLive() ? $article->published_at : now()]),
            'schedule' => $article->fill(['status' => 'scheduled', 'published_at' => $data['publish_at']]),
            'unpublish' => $article->fill(['status' => 'draft', 'published_at' => null]),
            default => null,
        };

        // Saved by hand, so a failed attempt by Claude is old news.
        if ($article->writing_status === 'failed') {
            $article->fill(['writing_status' => null, 'writing_error' => null]);
        }

        $article->save();
        $article->tags()->sync($data['tags'] ?? []);
        $article->keepRevision($note, $request->user());

        if ($action === 'schedule') {
            $message = 'Scheduled for '.$article->published_at->format('j M Y, g:i A').'.';
        }
        $logged = ['save' => 'updated', 'publish' => 'published', 'schedule' => 'scheduled', 'unpublish' => 'unpublished'][$action];
        ActivityLog::record("article.{$logged}", "{$note} the article “{$article->title}”", $article, $article->title);

        return redirect()->route('admin.articles.edit', $article)->with('status', $message);
    }

    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();
        ActivityLog::record('article.deleted', "Deleted the article “{$article->title}”", $article, $article->title);

        return redirect()->route('admin.articles.index')->with('status', 'Article deleted.');
    }

    public function revisions(Request $request, Article $article): View
    {
        $revisions = $article->revisions()->with('user')->get();

        return view('admin.articles.revisions', [
            'article' => $article,
            'revisions' => $revisions,
            'shown' => $revisions->firstWhere('id', $request->integer('revision')) ?? $revisions->first(),
        ]);
    }

    public function restore(Request $request, Article $article, ArticleRevision $revision): RedirectResponse
    {
        abort_unless($revision->article_id === $article->id, 404);

        // What's there now is itself kept, so a restore can be undone.
        $article->keepRevision('Before a restore', $request->user());
        $article->update($revision->only(Article::CONTENT));
        $article->keepRevision('Restored from '.$revision->created_at->format('j M, g:i A'), $request->user());

        ActivityLog::record('article.restored', "Restored “{$article->title}” to its {$revision->created_at->format('j M Y, g:i A')} version", $article, $article->title);

        return redirect()->route('admin.articles.edit', $article)
            ->with('status', 'Restored.'.($article->isLive() ? ' The article is live, so readers see this version now.' : ''));
    }

    private function briefRules(): array
    {
        return [
            'topic' => ['required', 'string', 'min:3', 'max:200'],
            'sources' => ['array', 'max:3'],
            'sources.*' => ['nullable', 'url:http,https', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'length' => ['nullable', Rule::in(array_keys(Article::LENGTHS))],
        ];
    }

    private function briefLabels(): array
    {
        return ['topic' => 'keyword or title', 'sources.*' => 'source link', 'article_category_id' => 'category'];
    }

    private function briefFrom(array $data): array
    {
        return [
            'topic' => $data['topic'],
            'sources' => collect($data['sources'] ?? [])->filter()->unique()->values()->all(),
            'instructions' => $data['instructions'] ?? null,
            'length' => $data['length'] ?? 'standard',
        ];
    }

    private function storeImage(UploadedFile $file, ?string $old): string
    {
        $name = 'article-'.Str::random(12).'.'.$file->extension();
        $file->move(public_path('uploads/articles'), $name);
        $this->deleteImage($old);

        return "uploads/articles/{$name}";
    }

    private function deleteImage(?string $path): void
    {
        // Only ever delete our own uploads.
        if ($path && Str::startsWith($path, 'uploads/articles/article-')) {
            File::delete(public_path($path));
        }
    }
}
