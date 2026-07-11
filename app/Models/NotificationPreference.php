<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperNotificationPreference
 */
class NotificationPreference extends Model
{
    protected $table = 'notification_preferences';

    protected $fillable = [
        'user_id', 'channels', 'types',
        'quiet_hours_start', 'quiet_hours_end', 'timezone',
        'digest_frequency', 'last_digest_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'types' => 'array',
            'last_digest_sent_at' => 'datetime',
        ];
    }

    const CHANNELS = ['mail', 'sms', 'database'];
    const CATEGORIES = [
        'license_expiry' => 'License 到期提醒',
        'invoice' => '发票/账单通知',
        'payment' => '支付通知',
        'security' => '安全提醒',
        'system' => '系统公告',
        'im_message' => '私信消息',
        'promotion' => '营销推广',
        'commission' => '佣金通知',
    ];
    const DIGEST_FREQUENCIES = ['none', 'daily', 'weekly', 'monthly'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取某个分类在某个渠道是否启用
     */
    public function isEnabled(string $category, string $channel): bool
    {
        $types = $this->types ?? [];
        $channels = $this->channels ?? [];

        if (isset($channels[$channel]) && !$channels[$channel]) {
            return false;
        }

        if (isset($types[$category][$channel])) {
            return (bool) $types[$category][$channel];
        }

        if ($category === 'security') return true;
        if ($category === 'promotion' && $channel !== 'database') return false;

        return true;
    }

    /**
     * 检查当前是否在免打扰时段
     */
    public function isInQuietHours(?Carbon $now = null): bool
    {
        if (empty($this->quiet_hours_start) || empty($this->quiet_hours_end)) {
            return false;
        }

        $now = $now ?? Carbon::now($this->timezone ?? 'Asia/Shanghai');
        $start = Carbon::parse($this->quiet_hours_start, $this->timezone ?? 'Asia/Shanghai');
        $end = Carbon::parse($this->quiet_hours_end, $this->timezone ?? 'Asia/Shanghai');

        if ($start <= $end) {
            // 同一天内，如 22:00-08:00
            return $now->between($start, $end);
        }

        // 跨天，如 22:00-06:00
        return $now->gte($start) || $now->lte($end);
    }

    /**
     * 获取当前是否为摘要发送时间
     */
    public function isDigestDue(?Carbon $now = null): bool
    {
        if ($this->digest_frequency === 'none') return false;

        $now = $now ?? Carbon::now($this->timezone ?? 'Asia/Shanghai');
        $lastSent = $this->last_digest_sent_at;

        return match ($this->digest_frequency) {
            'daily' => $lastSent === null || $lastSent->lt($now->copy()->startOfDay()),
            'weekly' => $lastSent === null || $lastSent->lt($now->copy()->startOfWeek()),
            'monthly' => $lastSent === null || $lastSent->lt($now->copy()->startOfMonth()),
            default => false,
        };
    }
}
