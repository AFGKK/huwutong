<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class PricingPlan extends Model
{
    protected $fillable = [
        'tenant_id', 'product_id', 'slug', 'name', 'description',
        'billing_period', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PricingPlanPrice::class);
    }

    /**
     * 获取指定货币的价格
     */
    public function getPrice(string $currency): ?PricingPlanPrice
    {
        return $this->prices()->where('currency', strtoupper($currency))->first();
    }

    /**
     * 获取所有货币维度价格，含格式化
     */
    public function getPricesGrouped(): Collection
    {
        return $this->prices->map(function (PricingPlanPrice $price) {
            return [
                'id' => $price->id,
                'currency' => $price->currency,
                'symbol' => ExchangeRate::symbol($price->currency),
                'price' => (float) $price->price,
                'price_formatted' => ExchangeRate::format((float) $price->price, $price->currency),
                'setup_fee' => (float) $price->setup_fee,
                'trial_price' => $price->trial_price ? (float) $price->trial_price : null,
            ];
        });
    }

    /**
     * scope: 活跃计划
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
