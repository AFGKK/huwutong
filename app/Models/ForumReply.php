<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumReply extends Model
{
    use SoftDeletes;
    protected $fillable = ['post_id', 'parent_id', 'user_id', 'content', 'likes_count'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function post(): BelongsTo { return $this->belongsTo(ForumPost::class, 'post_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
}
