<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperForumFavoriteCollection
 */
class ForumFavoriteCollection extends Model
{
    protected $table = 'forum_favorite_collections';
    protected $fillable = ['user_id', 'name', 'icon', 'sort_order'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(ForumFavorite::class, 'collection_id');
    }
}
