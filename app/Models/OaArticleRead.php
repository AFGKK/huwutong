<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOaArticleRead
 */
class OaArticleRead extends Model
{
    protected $fillable = ['article_id', 'user_id', 'ip', 'read_duration', 'scroll_depth', 'completed'];

    protected $casts = [
        'read_duration' => 'integer',
        'scroll_depth' => 'integer',
        'completed' => 'boolean',
    ];

    protected $table = 'oa_article_reads';

    public function article(): BelongsTo { return $this->belongsTo(OaArticle::class, 'article_id'); }
}
