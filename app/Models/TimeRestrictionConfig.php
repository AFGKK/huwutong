<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * License 使用时段限制配置 (M3-77)
 *
 * 支持：
 * - 每周可用时段（如仅工作日 9:00-18:00）
 * - 特定期日特殊时段
 * - 节假日日历
 * - 时区感知
 * - 宽限机制
 * - IP 白名单例外
 */
class TimeRestrictionConfig extends Model
{
    protected $fillable = [
        'restrictable_type',
        'restrictable_id',
        'is_active',
        'timezone',
        'weekly_schedule',
        'special_schedule',
        'holidays',
        'out_of_hours_action',
        'grace_minutes',
        'allowed_ip_ranges',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'weekly_schedule' => 'array',
            'special_schedule' => 'array',
            'holidays' => 'array',
            'grace_minutes' => 'integer',
        ];
    }

    public function restrictable(): MorphTo
    {
        return $this->morphTo();
    }
}
