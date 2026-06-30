<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RssFeed extends Model
{
    protected $fillable = [
        'feed_type', 'title', 'description', 'language', 'ttl',
    ];
}
