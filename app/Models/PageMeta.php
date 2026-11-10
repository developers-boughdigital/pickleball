<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** A search title and description someone wrote for one page, by path ("/" or "play/vic"). */
class PageMeta extends Model
{
    protected $table = 'page_meta';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['noindex' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('page_meta'));
        static::deleted(fn () => Cache::forget('page_meta'));
    }

    /** "/play/vic/", "play/vic" and "https://site/play/vic" all become "play/vic"; the home page is "/". */
    public static function normalise(string $path): string
    {
        $path = trim((string) parse_url($path, PHP_URL_PATH), '/');

        return $path === '' ? '/' : strtolower($path);
    }

    public static function forPath(string $path): ?self
    {
        // Read on every public page, so keep the (short) list in the cache. Plain
        // arrays: the cache won't unserialize objects (config/cache.php serializable_classes).
        $all = rescue(fn () => Cache::rememberForever('page_meta', fn () => static::all(['path', 'title', 'description', 'noindex'])
            ->mapWithKeys(fn (self $page) => [$page->path => $page->only(['path', 'title', 'description', 'noindex'])])
            ->all()), [], report: false);

        $row = $all[static::normalise($path)] ?? null;

        return $row ? (new static)->forceFill($row) : null;
    }
}
