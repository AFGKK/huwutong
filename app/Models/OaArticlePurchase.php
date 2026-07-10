<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOaArticlePurchase
 */
class OaArticlePurchase extends Model
{
    protected $table = 'oa_article_purchases';

    protected $fillable = [
        'article_id',
        'user_id',
        'price',
        'price_type',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(OaArticle::class, 'article_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
