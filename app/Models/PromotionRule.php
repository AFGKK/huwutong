<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperPromotionRule
 */
class PromotionRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'type', 'description',
        'condition_type', 'condition_value',
        'discount_value', 'max_discount', 'min_order_amount',
        'applicable_products', 'applicable_categories', 'excluded_products',
        'stackable_with_coupon', 'stackable_with_other_rules', 'priority',
        'usage_limit', 'usage_limit_per_customer', 'usage_count',
        'budget', 'budget_spent',
        'starts_at', 'ends_at',
        'tiers',
        'buy_quantity', 'free_quantity', 'free_products',
        'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'condition_value' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'applicable_products' => 'array',
            'applicable_categories' => 'array',
            'excluded_products' => 'array',
            'stackable_with_coupon' => 'boolean',
            'stackable_with_other_rules' => 'boolean',
            'priority' => 'integer',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'usage_count' => 'integer',
            'budget' => 'decimal:14',
            'budget_spent' => 'decimal:14',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'tiers' => 'array',
            'buy_quantity' => 'integer',
            'free_quantity' => 'integer',
            'free_products' => 'array',
        ];
    }

    const TYPES = [
        'amount_off' => '满减（减固定金额）',
        'percent_off' => '满折（打百分比折扣）',
        'buy_x_get_y' => '买N送N',
        'fixed_price' => '一口价',
    ];

    const CONDITION_TYPES = [
        'subtotal' => '按订单总额',
        'quantity' => '按商品数量',
        'items_count' => '按商品件数',
    ];

    const STATUSES = ['draft', 'active', 'paused', 'expired'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PromotionRuleRedemption::class);
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') return false;
        $now = now();
        if ($this->starts_at && $now < $this->starts_at) return false;
        if ($this->ends_at && $now > $this->ends_at) return false;
        return true;
    }

    public function hasBudgetLeft(): bool
    {
        if ($this->budget === null) return true;
        return $this->budget_spent < $this->budget;
    }

    public function hasUsageLeft(): bool
    {
        if ($this->usage_limit === null) return true;
        return $this->usage_count < $this->usage_limit;
    }
}
