<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 客户健康度评分
 *
 * 综合考量激活活跃度、续费健康度、工单体验、设备安全、支付健康度
 * 评分范围: 0.00 ~ 100.00
 * 等级: healthy (>=70) / warning (>=40) / critical (<40)
 */
class HealthScore extends Model
{
    const GRADE_HEALTHY = 'healthy';
    const GRADE_WARNING = 'warning';
    const GRADE_CRITICAL = 'critical';

    const THRESHOLD_HEALTHY = 70;
    const THRESHOLD_WARNING = 40;

    protected $fillable = [
        'tenant_id', 'customer_id',
        'score', 'grade',
        'activation_score', 'renewal_score', 'ticket_score',
        'device_score', 'payment_score',
        'factors', 'warnings', 'suggestions',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'activation_score' => 'decimal:2',
            'renewal_score' => 'decimal:2',
            'ticket_score' => 'decimal:2',
            'device_score' => 'decimal:2',
            'payment_score' => 'decimal:2',
            'factors' => 'array',
            'warnings' => 'array',
            'suggestions' => 'array',
            'calculated_at' => 'datetime',
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
