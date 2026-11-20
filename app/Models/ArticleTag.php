<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** A tag, which can sit under another tag: Technique › Serving › Spin serve. */
class ArticleTag extends Model
{
    protected $fillable = ['name', 'slug', 'parent_id'];

    protected static function booted(): void
    {
        static::saving(function (self $tag) {
            $tag->slug = Article::freeSlug(static::class, $tag->slug ?: $tag->name, $tag->id);
        });

        // Children move up a level rather than vanish with their parent.
        static::deleting(fn (self $tag) => static::where('parent_id', $tag->id)->update(['parent_id' => $tag->parent_id]));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_tag');
    }

    /**
     * Every tag in tree order, each with `depth` (0 for a top-level tag) and
     * `path` ("Technique › Serving"), ready for an indented list or select.
     *
     * @return Collection<int, self>
     */
    public static function tree(): Collection
    {
        $byParent = static::query()->withCount('articles')->orderBy('name')->get()->groupBy(fn (self $tag) => $tag->parent_id ?? 0);
        $ordered = collect();

        $walk = function (int $parentId, int $depth, string $path) use (&$walk, $byParent, $ordered) {
            foreach ($byParent[$parentId] ?? [] as $tag) {
                $tag->depth = $depth;
                $tag->path = $path === '' ? $tag->name : "{$path} › {$tag->name}";
                $ordered->push($tag);
                $walk($tag->id, $depth + 1, $tag->path);
            }
        };
        $walk(0, 0, '');

        return $ordered;
    }

    /**
     * This tag's id and the ids of everything under it. A tag can't be moved
     * under any of these, or it would become its own ancestor.
     *
     * @return array<int, int>
     */
    public function branchIds(): array
    {
        $ids = [$this->id];
        $level = [$this->id];

        while ($level = static::whereIn('parent_id', $level)->pluck('id')->all()) {
            $ids = [...$ids, ...$level];
        }

        return $ids;
    }
}
