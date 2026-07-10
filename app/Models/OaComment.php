<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOaComment
 */
class OaComment extends Model
{
    protected $fillable = ['article_id', 'user_id', 'content', 'image', 'parent_id', 'status', 'is_pinned'];

    protected $casts = [
        'created_at' => 'datetime',
        'is_pinned' => 'boolean',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(OaArticle::class, 'article_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function likes()
    {
        return $this->hasMany(OaCommentLike::class, 'comment_id');
    }

    public function isLikedBy(?int $userId): bool
    {
        return $userId !== null && $this->likes()->where('user_id', $userId)->exists();
    }
}
