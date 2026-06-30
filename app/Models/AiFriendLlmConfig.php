<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFriendLlmConfig extends Model
{
    protected $fillable = [
        'ai_friend_id', 'provider', 'model_name', 'api_base_url',
        'api_key_encrypted', 'system_prompt', 'temperature',
        'max_tokens', 'context_window', 'stream_enabled',
    ];

    protected $casts = [
        'temperature' => 'float',
        'stream_enabled' => 'boolean',
    ];

    public function profile()
    {
        return $this->belongsTo(AiFriendProfile::class, 'ai_friend_id');
    }
}
