<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 用量聚合
 *
 * 预聚合的用量数据，用于快速查询不同时间维度的用量统计。
 * 由定时任务或实时计数器更新。
 */
class UsageAggregate extends Model
{
    protected $fillable = [
        'tenant_id',
        'license_id',
        'customer_id',
        'metric_key',
        'period',
        'period_start',
        'period_end',
        'total_quantity',
        'record_count',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_quantity' => 'integer',
            'record_count' => 'integer',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
