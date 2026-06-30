<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 佣金计划明细
 *
 * 产品 × 等级 → 佣金比例
 */
class CommissionPlanItem extends Model
{
    protected $fillable = [
        'commission_plan_id', 'product_id', 'product_category',
        'agent_level', 'commission_rate', 'rate_type',
        'fixed_amount', 'tier_from_days', 'tier_to_days', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CommissionPlan::class, 'commission_plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 判断是否匹配指定产品和等级
     */
    public function matches(?int $productId, ?string $category, string $agentLevel): bool
    {
        if ($this->product_id && $this->product_id !== $productId) {
            return false;
        }
        if (! $this->product_id && $this->product_category && $this->product_category !== $category) {
            return false;
        }
        if ($this->agent_level && $this->agent_level !== $agentLevel) {
            return false;
        }
        return true;
    }
}
