<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 用量配额
 *
 * 定义 License/产品维度的用量上限，支持不同时间窗（总/日/月）和超额策略。
 */
class UsageQuota extends Model
{
    protected $fillable = [
        'tenant_id',
        'license_id',
        'product_id',
        'metric_key',
        'window_type',
        'quota_limit',
        'action_on_exceed',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quota_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 超额后的操作映射
     */
    const ACTION_BLOCK = 'block';    // 阻止操作
    const ACTION_WARN = 'warn';      // 仅告警
    const ACTION_LOG = 'log';        // 仅记录
}
