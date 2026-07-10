<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperMeteredTieredPricing
 */
class MeteredTieredPricing extends Model
{
    protected $table = 'metered_tiered_pricings';

    protected $fillable = [
        'tenant_id', 'product_id', 'metric_key', 'name',
        'billing_period', 'tier_type', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    const TIER_TYPES = ['volume' => '总量阶梯', 'graduated' => '梯度阶梯'];
    const BILLING_PERIODS = ['monthly' => '月度', 'yearly' => '年度', 'one_time' => '一次性'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function tiers(): HasMany { return $this->hasMany(MeteredTierPricingTier::class, 'tiered_pricing_id'); }

    /** 计算指定用量的费用 */
    public function calculateCost(int $totalUsage): float
    {
        $tiers = $this->tiers()->orderBy('from_unit')->get();
        if ($tiers->isEmpty()) return 0;

        $cost = 0.0;
        $remaining = $totalUsage;

        if ($this->tier_type === 'volume') {
            // 总量阶梯：整个用量按所在阶梯的单价计算
            $applicableTier = $tiers->first(fn($t) => $totalUsage >= $t->from_unit && ($t->to_unit === null || $totalUsage <= $t->to_unit));
            if (!$applicableTier) $applicableTier = $tiers->last();
            $cost = $totalUsage * $applicableTier->unit_price + $applicableTier->flat_fee;
        } else {
            // 梯度阶梯：逐级计算
            foreach ($tiers as $tier) {
                $tierRange = ($tier->to_unit ?? PHP_INT_MAX) - $tier->from_unit + 1;
                $tierUsage = min($remaining, $tierRange);
                if ($tierUsage <= 0) break;
                $cost += $tierUsage * $tier->unit_price;
                if ($tier->price_model === 'flat') $cost += $tier->flat_fee;
                $remaining -= $tierUsage;
                if ($remaining <= 0) break;
            }
        }

        return round($cost, 2);
    }
}
