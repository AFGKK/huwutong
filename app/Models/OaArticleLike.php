<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOaArticleLike
 */
class OaArticleLike extends Model
{
    protected $table = 'likes';
    protected $fillable = ['user_id', 'likeable_id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->likeable_type = 'App\\Models\\OaArticle';
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(OaArticle::class, 'likeable_id');
    }

    public static function create(array $attributes = [])
    {
        $attributes['likeable_type'] = 'App\\Models\\OaArticle';
        return static::query()->create($attributes);
    }

    public function scopeWhereArticleId($query, $articleId)
    {
        return $query->where('likeable_type', 'App\\Models\\OaArticle')->where('likeable_id', $articleId);
    }

    public function scopeWhereUserId($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
