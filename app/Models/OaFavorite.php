<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOaFavorite
 */
class OaFavorite extends Model
{
    protected $table = 'favorites';
    protected $fillable = ['user_id', 'favorable_id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->favorable_type = 'App\\Models\\OaArticle';
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(OaArticle::class, 'favorable_id');
    }

    public static function create(array $attributes = [])
    {
        $attributes['favorable_type'] = 'App\\Models\\OaArticle';
        return static::query()->create($attributes);
    }

    public function scopeWhereArticleId($query, $articleId)
    {
        return $query->where('favorable_type', 'App\\Models\\OaArticle')->where('favorable_id', $articleId);
    }

    public function scopeWhereUserId($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
