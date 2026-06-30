<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OaArticle extends Model
{
    use SoftDeletes;
    protected $fillable = ['account_id', 'author_id', 'title', 'content', 'cover_image', 'images', 'summary', 'tags', 'status', 'reviewer_id', 'reviewed_at', 'reject_reason', 'source_submission_id', 'is_pinned', 'is_original', 'allow_comments', 'published_at', 'edited_at', 'scheduled_at'];
    protected $casts = ['tags' => 'array', 'images' => 'array', 'is_pinned' => 'boolean', 'is_original' => 'boolean', 'allow_comments' => 'boolean', 'published_at' => 'datetime', 'edited_at' => 'datetime', 'scheduled_at' => 'datetime'];
    protected $table = 'oa_articles';

    public function account(): BelongsTo { return $this->belongsTo(OfficialAccount::class, 'account_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
    public function likes(): MorphMany { return $this->morphMany(Like::class, 'likeable'); }
    public function reads(): HasMany { return $this->hasMany(OaArticleRead::class, 'article_id'); }
    public function shares(): HasMany { return $this->hasMany(OaArticleShare::class, 'article_id'); }
    public function comments(): HasMany { return $this->hasMany(OaComment::class, 'article_id'); }
    public function favorites(): MorphMany { return $this->morphMany(Favorite::class, 'favorable'); }
    public function collection(): BelongsTo { return $this->belongsTo(OaCollection::class, 'collection_id'); }
    public function likeCount(): int { return $this->likes()->count(); }
    public function readCount(): int { return $this->reads()->count(); }
    public function isLikedBy(int $userId): bool { return $this->likes()->where('user_id', $userId)->exists(); }
}
