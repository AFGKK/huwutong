<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperForumLike
 */
class ForumLike extends Model
{
    protected $table = 'likes';
    protected $fillable = ['user_id', 'likeable_type', 'likeable_id'];

    public function likeable(): MorphTo { return $this->morphTo(); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
}
