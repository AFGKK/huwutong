<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 社区关注关系（forum_follows）
 *
 * @mixin IdeHelperForumFollow
 */
class ForumFollow extends Model
{
    protected $table = 'forum_follows';

    protected $fillable = [
        'user_id',
        'target_user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
