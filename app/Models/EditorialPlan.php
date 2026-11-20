<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A row on the editorial calendar: a topic we mean to publish on a date. */
class EditorialPlan extends Model
{
    protected $fillable = ['planned_for', 'topic', 'article_category_id', 'notes', 'article_id', 'created_by'];

    protected function casts(): array
    {
        return ['planned_for' => 'date'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
