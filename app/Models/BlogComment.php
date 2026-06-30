<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogComment extends Model
{
    protected $fillable = ['blog_id', 'user_id', 'parent_id', 'content', 'image', 'likes_count', 'is_pinned'];

    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    public function blog(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}