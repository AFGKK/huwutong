<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 定价方案模型
 *
 * 管理产品定价策略，支持多周期定价、功能列表、使用限制。
 */
class PricingPlan extends Model
{
    use HasFactory, \App\Models\Concerns\HasProductTranslations;

    protected $fillable = [
        'tenant_id', 'product_id',
        'slug', 'name', 'description',
        'currency',
        'price_monthly', 'price_quarterly', 'price_semi_annually', 'price_yearly',
        'features', 'limits',
        'trial_days', 'sort_order',
        'is_public', 'is_active', 'badge',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'metadata' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'price_monthly' => 'decimal:2',
            'price_quarterly' => 'decimal:2',
            'price_semi_annually' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'trial_days' => 'integer',
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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'pricing_plan_slug', 'slug');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(PricingPlanHistory::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PricingPlanPrice::class, 'pricing_plan_id');
    }

    /**
     * 获取指定周期的价格
     */
    public function getPrice(string $billingPeriod): float
    {
        $field = match ($billingPeriod) {
            'monthly' => 'price_monthly',
            'quarterly' => 'price_quarterly',
            'semi_annually' => 'price_semi_annually',
            'yearly' => 'price_yearly',
            default => 'price_monthly',
        };

        return (float) ($this->$field ?? 0);
    }

    /**
     * 获取所有可用周期的价格
     */
    public function getPrices(): array
    {
        return [
            'monthly' => (float) ($this->price_monthly ?? 0),
            'quarterly' => (float) ($this->price_quarterly ?? 0),
            'semi_annually' => (float) ($this->price_semi_annually ?? 0),
            'yearly' => (float) ($this->price_yearly ?? 0),
        ];
    }

    /**
     * 获取年化价格
     */
    public function getAnnualEquivalent(): float
    {
        $monthly = (float) ($this->price_monthly ?? 0);
        return round($monthly * 12, 2);
    }

    /**
     * 获取方案摘要（用于前端选择）
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'badge' => $this->badge,
            'prices' => $this->getPrices(),
            'currency' => $this->currency,
            'features' => $this->features ?? [],
            'limits' => $this->limits ?? [],
            'trial_days' => $this->trial_days,
            'is_active' => $this->is_active,
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price_monthly');
    }
}
