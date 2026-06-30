<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OaCommentLike extends Model
{
    protected $fillable = ['comment_id', 'user_id'];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(OaComment::class, 'comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
