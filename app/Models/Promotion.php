<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'type', 'description',
        'status', 'rules',
        'discount_type', 'discount_value', 'max_discount', 'min_order_amount',
        'applicable_plans', 'applicable_products', 'applicable_billing_periods',
        'usage_limit', 'usage_limit_per_customer', 'usage_count',
        'budget', 'budget_spent',
        'starts_at', 'ends_at', 'published_at',
        'display_config', 'metadata', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'discount_value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'applicable_plans' => 'array',
            'applicable_products' => 'array',
            'applicable_billing_periods' => 'array',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'budget' => 'decimal:2',
            'budget_spent' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'display_config' => 'array',
            'metadata' => 'array',
        ];
    }

    const TYPES = [
        'flash_sale' => '限时秒杀',
        'bulk_discount' => '批量折扣',
        'bundle' => '捆绑销售',
        'x_for_y' => '买X送Y',
        'free_gift' => '赠送礼品',
        'tiered' => '阶梯优惠',
    ];

    const STATUSES = ['draft', 'active', 'paused', 'expired', 'cancelled'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions()
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->starts_at <= now()
            && (!$this->ends_at || $this->ends_at >= now());
    }

    public function hasBudget(): bool
    {
        return !$this->budget || $this->budget_spent < $this->budget;
    }

    public function hasUsageLeft(): bool
    {
        return !$this->usage_limit || $this->usage_count < $this->usage_limit;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function calculateDiscount(float $originalAmount): float
    {
        return match ($this->discount_type) {
            'percentage' => min($originalAmount * ($this->discount_value / 100), $this->max_discount ?? PHP_FLOAT_MAX),
            'fixed_amount' => min($this->discount_value, $this->max_discount ?? $this->discount_value),
            'free' => $originalAmount,
            default => 0,
        };
    }
}
