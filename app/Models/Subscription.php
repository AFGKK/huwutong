<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Subscription billing model
 *
 * 订阅计费核心模型，管理订阅生命周期、自动续费、宽限期等
 */
class Subscription extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'product_id',
        'status', 'plan', 'price', 'currency', 'billing_period',
        'starts_at', 'ends_at', 'trial_ends_at',
        'grace_days', 'grace_ends_at',
        'auto_renew', 'canceled_at', 'cancellation_reason',
        'payment_info', 'metadata',
        'last_billed_at', 'next_billing_at',
        'billing_cycles_completed', 'total_paid', 'pricing_plan_slug',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'last_billed_at' => 'datetime',
            'next_billing_at' => 'datetime',
            'auto_renew' => 'boolean',
            'payment_info' => 'array',
            'metadata' => 'array',
            'price' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'billing_cycles_completed' => 'integer',
            'grace_days' => 'integer',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * 订阅是否处于活跃状态
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * 是否在宽限期内
     */
    public function isInGracePeriod(): bool
    {
        return $this->status === 'grace'
            && $this->grace_ends_at
            && now()->lessThanOrEqualTo($this->grace_ends_at);
    }

    /**
     * 是否已过期
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->ends_at && now()->greaterThan($this->ends_at) && !$this->isInGracePeriod());
    }

    /**
     * 是否处于试用期
     */
    public function isInTrial(): bool
    {
        return $this->trial_ends_at && now()->lessThan($this->trial_ends_at);
    }

    /**
     * 是否需要自动续费
     */
    public function needsRenewal(): bool
    {
        return $this->auto_renew
            && $this->status === 'active'
            && $this->next_billing_at
            && now()->greaterThanOrEqualTo($this->next_billing_at);
    }

    /**
     * 获取下次账单金额（含税）
     */
    public function getNextBillingAmount(): float
    {
        return (float) $this->price;
    }

    /**
     * 计算续费周期结束时间
     */
    public function calculateRenewalEndDate(): Carbon
    {
        $base = $this->ends_at ?: now();

        return match ($this->billing_period) {
            'monthly' => $base->copy()->addMonth(),
            'quarterly' => $base->copy()->addMonths(3),
            'semi_annually' => $base->copy()->addMonths(6),
            'yearly' => $base->copy()->addYear(),
            default => $base->copy()->addMonth(),
        };
    }

    /**
     * 激活订阅
     */
    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * 暂停订阅
     */
    public function suspend(): void
    {
        $this->update(['status' => 'suspended']);
    }

    /**
     * 取消订阅（在当前周期结束后不再续费）
     */
    public function cancel(?string $reason = null): void
    {
        $this->update([
            'auto_renew' => false,
            'canceled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * 进入宽限期
     */
    public function enterGracePeriod(): void
    {
        $this->update([
            'status' => 'grace',
            'grace_ends_at' => now()->addDays($this->grace_days ?: 7),
        ]);
    }

    /**
     * 标记为已过期
     */
    public function markExpired(): void
    {
        $this->update(['status' => 'expired']);
    }
}
