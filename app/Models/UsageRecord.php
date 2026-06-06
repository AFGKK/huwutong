<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 用量记录
 *
 * 记录每一次可计量的操作，支持按次/按量/按时间窗统计。
 * 是用量计量系统的基础数据单元。
 */
class UsageRecord extends Model
{
    protected $fillable = [
        'tenant_id',
        'license_id',
        'customer_id',
        'metric_key',
        'action',
        'window_type',
        'quantity',
        'unit',
        'context',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'context' => 'array',
            'recorded_at' => 'datetime',
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
