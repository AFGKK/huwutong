<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperKbArticleVersion
 */
class KbArticleVersion extends Model
{
    protected $fillable = [
        'article_id', 'author_id', 'version_number',
        'content', 'change_summary',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
