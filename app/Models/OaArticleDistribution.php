<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOaArticleDistribution
 */
class OaArticleDistribution extends Model
{
    protected $table = 'oa_article_distributions';

    protected $fillable = [
        'article_id', 'platform_account_id', 'platform',
        'status', 'external_id', 'external_url',
        'error_message', 'platform_data', 'published_at',
    ];

    protected $casts = [
        'platform_data' => 'json',
        'published_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(OaArticle::class, 'article_id');
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(OaPlatformAccount::class, 'platform_account_id');
    }
}
