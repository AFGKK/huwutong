<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperCart
 */
class Cart extends Model
{
    protected $fillable = [
        'user_id', 'tenant_id', 'session_id',
        'coupon_code', 'coupon_id', 'coupon_discount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'coupon_discount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * 计算商品小计（不含优惠券折扣）
     */
    public function getSubtotalAttribute(): float
    {
        return $this->items->sum(fn($item) => ($item->unit_price ?? 0) * $item->quantity);
    }

    /**
     * 计算优惠券折扣金额
     */
    public function getCouponDiscountAmountAttribute(): float
    {
        return (float) ($this->coupon_discount ?? 0);
    }

    /**
     * 计算最终金额
     */
    public function getFinalAmountAttribute(): float
    {
        return max(0, $this->subtotal - $this->coupon_discount_amount);
    }

    /**
     * 获取商品总数
     */
    public function getCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * 获取商品种类数
     */
    public function getItemCountAttribute(): int
    {
        return $this->items->count();
    }
}
