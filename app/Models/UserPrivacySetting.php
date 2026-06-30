<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPrivacySetting extends Model
{
    protected $fillable = [
        'user_id',
        'friend_add_policy',
        'show_online_status',
        'show_read_receipt',
        'allow_stranger_message',
        'privacy_pin',
    ];

    protected $casts = [
        'show_online_status' => 'boolean',
        'show_read_receipt' => 'boolean',
        'allow_stranger_message' => 'boolean',
    ];

    const FRIEND_ADD_POLICIES = ['everyone' => '所有人', 'need_question' => '需回答问题', 'nobody' => '不允许'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function defaultFor(int $userId): self
    {
        return static::firstOrCreate(['user_id' => $userId]);
    }
}
