<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumCategory extends Model
{
    protected $fillable = ['name', 'icon', 'sort_order'];
    protected $table = 'forum_categories';
}
