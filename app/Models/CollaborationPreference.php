<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollaborationPreference extends Model
{
    protected $table = 'collaboration_preferences';

    protected $fillable = [
        'user_id',
        'notify_on_mention',
        'notify_on_note_reply',
        'notify_on_status_change',
        'daily_digest',
        'digest_time',
    ];

    protected function casts(): array
    {
        return [
            'notify_on_mention' => 'boolean',
            'notify_on_note_reply' => 'boolean',
            'notify_on_status_change' => 'boolean',
            'daily_digest' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取或创建用户的协作偏好
     */
    public static function forUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'notify_on_mention' => true,
                'notify_on_note_reply' => true,
                'notify_on_status_change' => true,
                'daily_digest' => false,
                'digest_time' => '09:00',
            ]
        );
    }
}
