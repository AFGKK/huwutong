<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAiFriendProfile
 */
class AiFriendProfile extends Model
{
    protected $fillable = [
        'user_id', 'visibility', 'creator_id', 'tenant_id',
        'category', 'welcome_message', 'description', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function llmConfig()
    {
        return $this->hasOne(AiFriendLlmConfig::class, 'ai_friend_id');
    }

    public function contacts()
    {
        return $this->hasMany(UserAiContact::class, 'ai_friend_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function scopeGlobal($query)
    {
        return $query->where('visibility', 'global')->whereNotNull('published_at');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
