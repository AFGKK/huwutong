<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperBlogRead
 */
class BlogRead extends Model
{
    protected $fillable = ['blog_id', 'user_id', 'ip'];
    protected $table = 'blog_reads';

    public function blog(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
