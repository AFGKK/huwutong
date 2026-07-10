<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperForumCategory
 */
class ForumCategory extends Model
{
    protected $fillable = ['name', 'icon', 'sort_order'];
    protected $table = 'forum_categories';
}
