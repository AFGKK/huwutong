<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperRssFeed
 */
class RssFeed extends Model
{
    protected $fillable = [
        'feed_type', 'title', 'description', 'language', 'ttl',
    ];
}
