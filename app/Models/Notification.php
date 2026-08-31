<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperNotification
 */
class Notification extends Model
{
    /** 互动通知类型（赞 / 评论 / @ / 关注） */
    public const INTERACTION_TYPES = [
        'interaction_like',
        'interaction_comment',
        'interaction_mention',
        'interaction_follow',
    ];

    /** 私信推送类（消息列表分区里不归入「系统/官方」） */
    public const IM_TYPES = [
        'im_message',
    ];

    public static function isInteractionType(?string $type): bool
    {
        return in_array($type, self::INTERACTION_TYPES, true);
    }

    public static function isSystemHubType(?string $type): bool
    {
        if ($type === null || $type === '') {
            return false;
        }

        return ! self::isInteractionType($type) && ! in_array($type, self::IM_TYPES, true);
    }

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'user_id',
        'type',
        'group_key',
        'title',
        'content',
        'payload',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
