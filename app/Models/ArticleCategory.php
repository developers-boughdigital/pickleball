<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    protected static function booted(): void
    {
        static::saving(function (self $category) {
            $category->slug = Article::freeSlug(static::class, $category->slug ?: $category->name, $category->id);
        });
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
