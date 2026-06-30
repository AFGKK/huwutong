<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 优惠券模型
 *
 * 支持百分比折扣、固定金额折扣、免费试用等多种类型。
 */
class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'code', 'name', 'description',
        'type', 'value', 'currency',
        'minimum_order_amount', 'maximum_discount',
        'usage_limit', 'usage_limit_per_user', 'usage_count',
        'applicable_plans', 'applicable_products', 'applicable_billing_periods',
        'is_redeemable_with_other_coupons',
        'status', 'starts_at', 'expires_at',
        'first_redeemed_at', 'last_redeemed_at',
        'promotion_id', 'is_stackable', 'auto_apply', 'priority',
        'budget', 'budget_spent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'maximum_discount' => 'decimal:2',
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'usage_count' => 'integer',
            'applicable_plans' => 'array',
            'applicable_products' => 'array',
            'applicable_billing_periods' => 'array',
            'is_redeemable_with_other_coupons' => 'boolean',
            'is_stackable' => 'boolean',
            'auto_apply' => 'boolean',
            'priority' => 'integer',
            'budget' => 'decimal:2',
            'budget_spent' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'first_redeemed_at' => 'datetime',
            'last_redeemed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * 检查优惠券在指定金额下是否有效
     */
    public function isValid(?float $amount = null, ?string $customerId = null, ?string $plan = null, ?string $productId = null): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();

        // 时间范围检查
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->expires_at && $now->gt($this->expires_at)) {
            return false;
        }

        // 总使用次数
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        // 最低订单金额
        if ($amount !== null && $this->minimum_order_amount && $amount < $this->minimum_order_amount) {
            return false;
        }

        // 适用方案
        if ($plan !== null && !empty($this->applicable_plans)) {
            if (!in_array($plan, $this->applicable_plans)) {
                return false;
            }
        }

        // 适用产品
        if ($productId !== null && !empty($this->applicable_products)) {
            if (!in_array((string)$productId, $this->applicable_products)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 计算折扣金额
     */
    public function calculateDiscount(float $originalAmount): float
    {
        $discount = match ($this->type) {
            'percentage' => $originalAmount * ($this->value / 100),
            'fixed_amount' => $this->value,
            'free_trial' => $originalAmount, // 首单免费
            default => 0,
        };

        // 最高折扣限制
        if ($this->maximum_discount && $discount > $this->maximum_discount) {
            $discount = (float) $this->maximum_discount;
        }

        return round(max(0, $discount), 2);
    }

    /**
     * 记录使用
     */
    public function recordRedemption(CouponRedemption $redemption): void
    {
        $this->increment('usage_count');

        if (!$this->first_redeemed_at) {
            $this->first_redeemed_at = now();
        }
        $this->last_redeemed_at = now();

        $this->save();
    }

    /**
     * 检查用户是否已达到使用上限
     */
    public function hasReachedUserLimit(int $customerId): bool
    {
        if (!$this->usage_limit_per_user) {
            return false;
        }

        return CouponRedemption::where('coupon_id', $this->id)
            ->where('customer_id', $customerId)
            ->count() >= $this->usage_limit_per_user;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeValid($query)
    {
        $now = now();
        return $query->where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }
}
