<?php

namespace App\Models;

use App\Support\Articles\Shortcodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * An article on /learn. Written by hand or drafted by Claude from a brief,
 * then edited, and published now or on a date.
 */
class Article extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
    ];

    /** Length options on the "New article" form => roughly how many words Claude aims for. */
    public const LENGTHS = [
        'short' => ['Short', 600],
        'standard' => ['Standard', 1200],
        'long' => ['In depth', 2000],
    ];

    /** The words. A change to any of these on save is kept as a revision. */
    public const CONTENT = ['title', 'excerpt', 'body', 'meta_title', 'meta_description'];

    /** How many revisions to keep for each article. */
    public const REVISIONS_KEPT = 30;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'writing_started_at' => 'datetime',
            'brief' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $article) {
            // Once an article is published its address stays put, so links to it keep working.
            if ($article->exists && $article->isDirty('slug') && $article->getOriginal('status') === 'published') {
                $article->slug = $article->getOriginal('slug');
            }

            if (blank($article->slug) || $article->isDirty('slug')) {
                $article->slug = static::freeSlug(static::class, $article->slug ?: $article->title, $article->id);
            }
        });
    }

    /** A slug no other row of $model uses: "serving-tips", then "serving-tips-2". */
    public static function freeSlug(string $model, string $text, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($text), 90, '') ?: 'untitled';
        $query = $model::query()->where('slug', 'like', "{$base}%")->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId));
        $taken = (in_array(SoftDeletes::class, class_uses_recursive($model)) ? $query->withTrashed() : $query)->pluck('slug')->flip();

        $slug = $base;
        for ($i = 2; $taken->has($slug); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ArticleTag::class, 'article_tag');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class)->latest('id');
    }

    /**
     * What readers can see: published, or scheduled for a time that has passed.
     * Checking the clock here means a scheduled article goes live on time even
     * on a host with no cron job.
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now());
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['published', 'scheduled']) && $this->published_at?->lte(now());
    }

    /** Mark scheduled articles whose time has come as published. Tidies the lists; readers already see them. */
    public static function publishDue(): int
    {
        return static::where('status', 'scheduled')->where('published_at', '<=', now())->update(['status' => 'published']);
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'published' => 'badge-success',
            'scheduled' => 'badge-pending',
            default => 'badge-muted',
        };
    }

    /** Claude has been asked for a draft and hasn't finished. */
    public function isBeingWritten(): bool
    {
        return in_array($this->writing_status, ['pending', 'writing']);
    }

    /** "Writing" for too long means the request died; let the editor start it again. */
    public function writingStalled(): bool
    {
        return $this->writing_status === 'writing' && $this->writing_started_at?->lt(now()->subMinutes(12));
    }

    /**
     * Keep the article's words as a revision, unless nothing changed since the last one.
     */
    public function keepRevision(string $note, ?User $user = null): ?ArticleRevision
    {
        $now = $this->only(self::CONTENT);
        $last = $this->revisions()->first();

        if ($last && $last->only(self::CONTENT) == $now) {
            return null;
        }

        $revision = $this->revisions()->create([...$now, 'note' => $note, 'user_id' => $user?->id, 'created_at' => now()]);

        $old = $this->revisions()->skip(self::REVISIONS_KEPT)->take(100)->pluck('id');
        $old->isNotEmpty() && ArticleRevision::whereIn('id', $old)->delete();

        return $revision;
    }

    public function url(): string
    {
        return route('learn.show', $this->slug);
    }

    /** The body as readers see it, with shortcodes turned into cards, buttons and lists. */
    public function renderedBody(): string
    {
        return Shortcodes::render((string) $this->body);
    }

    public function wordCount(): int
    {
        return str_word_count(strip_tags((string) $this->body));
    }

    public function readingMinutes(): int
    {
        return max(1, (int) round($this->wordCount() / 220));
    }

    public function imageUrl(): ?string
    {
        return $this->featured_image ? asset($this->featured_image) : null;
    }
}
