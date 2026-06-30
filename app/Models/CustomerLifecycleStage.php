<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLifecycleStage extends Model
{
    use HasFactory;

    protected $table = 'customer_lifecycle_stages';

    protected $fillable = [
        'tenant_id', 'customer_id', 'stage', 'previous_stage',
        'reason', 'triggered_by', 'metadata',
        'entered_at', 'exited_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
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

    // 生命周期阶段定义
    const STAGES = [
        'prospect' => '潜在客户',
        'onboarding' => '引导期',
        'active' => '活跃期',
        'growing' => '成长期',
        'mature' => '成熟期',
        'at_risk' => '风险期',
        'churned' => '已流失',
    ];

    const STAGE_FLOW = [
        'prospect' => ['onboarding'],
        'onboarding' => ['active', 'at_risk', 'churned'],
        'active' => ['growing', 'at_risk', 'mature', 'churned'],
        'growing' => ['mature', 'at_risk', 'churned'],
        'mature' => ['at_risk', 'churned'],
        'at_risk' => ['active', 'churned'],
        'churned' => ['prospect'],
    ];

    const STAGE_ORDER = [
        'prospect' => 1,
        'onboarding' => 2,
        'active' => 3,
        'growing' => 4,
        'mature' => 5,
        'at_risk' => 6,
        'churned' => 7,
    ];
}
