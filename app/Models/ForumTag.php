<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperForumTag
 */
class ForumTag extends Model
{
    protected $table = 'forum_tags';
    protected $fillable = ['name', 'slug', 'sort_order'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(ForumPost::class, 'forum_post_tag', 'tag_id', 'post_id');
    }
}
