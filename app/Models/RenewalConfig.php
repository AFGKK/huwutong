<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RenewalConfig extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'max_attempts',
        'retry_intervals_days',
        'downgrade_after_attempt',
        'escalate_after_attempt',
        'notification_channels',
        'reminder_days_before',
        'reminder_schedule',
        'retention_coupon_enabled',
        'retention_coupon_discount_percent',
        'retention_coupon_max_uses',
        'retention_coupon_valid_days',
        'retention_coupon_max_discount',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_attempts' => 'integer',
        'retry_intervals_days' => 'array',
        'downgrade_after_attempt' => 'integer',
        'escalate_after_attempt' => 'integer',
        'notification_channels' => 'array',
        'reminder_days_before' => 'integer',
        'reminder_schedule' => 'array',
        'retention_coupon_enabled' => 'boolean',
        'retention_coupon_discount_percent' => 'decimal:2',
        'retention_coupon_max_uses' => 'integer',
        'retention_coupon_valid_days' => 'integer',
        'retention_coupon_max_discount' => 'decimal:2',
    ];

    /**
     * 获取启用的默认配置
     */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * 获取重试间隔（天）
     */
    public function getRetryIntervals(): array
    {
        return $this->retry_intervals_days ?? [3, 7, 7];
    }

    /**
     * 获取提醒节奏（过期前天数）
     */
    public function getReminderSchedule(): array
    {
        return $this->reminder_schedule ?? [30, 14, 7, 3, 1];
    }

    /**
     * 获取通知渠道
     */
    public function getNotificationChannels(): array
    {
        return $this->notification_channels ?? ['database', 'mail', 'sms'];
    }
}
