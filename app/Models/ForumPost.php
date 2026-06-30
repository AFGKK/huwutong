<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumPost extends Model
{
    use SoftDeletes;
    protected $fillable = ['category_id', 'user_id', 'title', 'content', 'images', 'video', 'views_count', 'likes_count', 'replies_count', 'is_pinned', 'is_locked', 'status', 'scheduled_at', 'template'];
    protected $casts = ['is_pinned' => 'boolean', 'is_locked' => 'boolean', 'images' => 'array', 'scheduled_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function category(): BelongsTo { return $this->belongsTo(ForumCategory::class, 'category_id'); }
    public function replies(): HasMany { return $this->hasMany(ForumReply::class, 'post_id'); }
    public function latestReply(): BelongsTo { return $this->belongsTo(ForumReply::class, 'last_reply_id'); }
    public function likes(): MorphMany { return $this->morphMany(ForumLike::class, 'likeable'); }
    public function favorites(): HasMany { return $this->hasMany(ForumFavorite::class, 'post_id'); }
    public function tags(): BelongsToMany { return $this->belongsToMany(ForumTag::class, 'forum_post_tag', 'post_id', 'tag_id'); }
    public function poll(): HasOne { return $this->hasOne(ForumPoll::class, 'post_id'); }
}
