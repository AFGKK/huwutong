<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperForumPost
 */
class ForumPost extends Model
{
    use SoftDeletes;
    protected $fillable = ['category_id', 'user_id', 'title', 'content', 'images', 'video', 'views_count', 'likes_count', 'replies_count', 'is_pinned', 'is_locked', 'status', 'scheduled_at', 'template', 'is_paid', 'price', 'price_type', 'content_preview'];
    protected $casts = ['is_pinned' => 'boolean', 'is_locked' => 'boolean', 'is_paid' => 'boolean', 'images' => 'array', 'scheduled_at' => 'datetime', 'price' => 'decimal:2'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function category(): BelongsTo { return $this->belongsTo(ForumCategory::class, 'category_id'); }
    public function replies(): HasMany { return $this->hasMany(ForumReply::class, 'post_id'); }
    public function latestReply(): BelongsTo { return $this->belongsTo(ForumReply::class, 'last_reply_id'); }
    public function purchases(): HasMany { return $this->hasMany(ForumPostPurchase::class, 'post_id'); }
    public function isPurchasedBy(int $userId): bool {
        return $this->purchases()->where('user_id', $userId)->where('status', 'completed')->exists();
    }
    public function likes(): MorphMany { return $this->morphMany(ForumLike::class, 'likeable'); }
    public function favorites(): HasMany { return $this->hasMany(ForumFavorite::class, 'post_id'); }
    public function reactions(): HasMany { return $this->hasMany(ForumReaction::class, 'post_id'); }
    public function tags(): BelongsToMany { return $this->belongsToMany(ForumTag::class, 'forum_post_tag', 'post_id', 'tag_id'); }
    public function poll(): HasOne { return $this->hasOne(ForumPoll::class, 'post_id'); }
}
