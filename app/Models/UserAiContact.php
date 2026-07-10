<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperUserAiContact
 */
class UserAiContact extends Model
{
    protected $fillable = [
        'user_id', 'ai_friend_id', 'source',
        'remark_name', 'is_pinned', 'is_hidden',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_hidden' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function aiFriend()
    {
        return $this->belongsTo(AiFriendProfile::class, 'ai_friend_id');
    }
}
