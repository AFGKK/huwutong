<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DunningStrategy extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'stages',
        'max_attempts', 'is_active', 'applicable_plans', 'sort_order',
    ];

    protected $table = 'dunning_strategies';

    protected function casts(): array
    {
        return [
            'stages' => 'array',
            'is_active' => 'boolean',
            'applicable_plans' => 'array',
            'sort_order' => 'integer',
            'max_attempts' => 'integer',
        ];
    }

    public function queueItems(): HasMany
    {
        return $this->hasMany(DunningQueue::class, 'dunning_strategy_id');
    }

    /**
     * 获取策略的催缴阶段定义
     * 每个阶段: {day, action, channel, subject?, template?}
     */
    public function getStages(): array
    {
        return $this->stages ?? $this->defaultStages();
    }

    /**
     * 默认催缴阶段
     */
    public static function defaultStages(): array
    {
        return [
            ['day' => 0, 'action' => 'send_reminder', 'channel' => 'email', 'subject' => '付款提醒 — 您的账单即将到期'],
            ['day' => 3, 'action' => 'send_reminder', 'channel' => 'email', 'subject' => '付款提醒 — 账单已逾期'],
            ['day' => 7, 'action' => 'send_warning', 'channel' => 'email_and_sms', 'subject' => '逾期警告 — 请尽快付款'],
            ['day' => 14, 'action' => 'retry_payment', 'channel' => 'payment_gateway', 'subject' => '正在尝试重新扣款'],
            ['day' => 21, 'action' => 'downgrade', 'channel' => 'email', 'subject' => '服务已降级 — 部分功能受限'],
            ['day' => 30, 'action' => 'suspend', 'channel' => 'email_and_sms', 'subject' => '服务已暂停'],
            ['day' => 45, 'action' => 'escalate', 'channel' => 'email', 'subject' => '人工跟进'],
        ];
    }

    /**
     * 获取策略阶段数
     */
    public function stageCount(): int
    {
        return count($this->getStages());
    }
}
