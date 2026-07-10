<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOaReadingListItem
 */
class OaReadingListItem extends Model
{
    protected $table = 'oa_reading_list';
    protected $fillable = ['user_id', 'article_id', 'notes', 'sort_order'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(OaArticle::class, 'article_id');
    }
}
