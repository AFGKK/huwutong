<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertRule extends Model
{
    protected $table = 'alert_rules';

    protected $fillable = [
        'name', 'slug', 'description',
        'metric_type', 'condition_operator', 'threshold', 'duration_minutes',
        'severity', 'channels', 'webhook_urls',
        'slack_webhook', 'dingtalk_webhook',
        'cooldown_minutes', 'max_alert_per_day',
        'is_active', 'filters',
        'last_triggered_at', 'daily_count', 'daily_count_date',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'decimal:4',
            'duration_minutes' => 'integer',
            'cooldown_minutes' => 'integer',
            'max_alert_per_day' => 'integer',
            'is_active' => 'boolean',
            'channels' => 'array',
            'webhook_urls' => 'array',
            'filters' => 'array',
            'last_triggered_at' => 'datetime',
            'daily_count' => 'integer',
            'daily_count_date' => 'date',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(AlertEvent::class, 'alert_rule_id');
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(AlertChannel::class, 'alert_channel_rule');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(AlertEscalation::class, 'alert_rule_id');
    }

    /**
     * 检查冷却期是否已过
     */
    public function isCooldownPassed(): bool
    {
        if (!$this->last_triggered_at) return true;
        return $this->last_triggered_at->diffInMinutes(now()) >= $this->cooldown_minutes;
    }

    /**
     * 检查每日限额是否未超
     */
    public function isDailyLimitOk(): bool
    {
        if ($this->max_alert_per_day <= 0) return true;
        if ($this->daily_count_date !== today()->toDateString()) {
            $this->update(['daily_count' => 0, 'daily_count_date' => today()]);
            return true;
        }
        return $this->daily_count < $this->max_alert_per_day;
    }

    /**
     * 前置检查：是否可以触发告警
     */
    public function canFire(): bool
    {
        return $this->is_active && $this->isCooldownPassed() && $this->isDailyLimitOk();
    }

    /**
     * 记录触发
     */
    public function recordFire(): void
    {
        $this->increment('daily_count');
        $this->update(['last_triggered_at' => now(), 'daily_count_date' => today()]);
    }
}
