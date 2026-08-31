<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperRssFeed
 */
class RssFeed extends Model
{
    use HasFactory;

    protected $fillable = [
        'feed_type', 'title', 'description', 'language', 'ttl',
    ];
}
