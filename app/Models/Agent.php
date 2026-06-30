<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 代理/分销商
 *
 * 一个用户最多可成为一个代理。代理拥有多个下级客户和推广链接。
 */
class Agent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'agent_code', 'level', 'status',
        'commission_rate', 'total_earned', 'total_withdrawn',
        'contact_name', 'contact_phone', 'company', 'notes',
        'approved_at',
        'tier_subscriptions_total', 'tier_revenue_total',
        'tier_referrals_total', 'tier_monthly_revenue',
        'tier_last_promoted_at', 'tier_next_review_at',
        'parent_agent_id', 'multi_level_rate', 'downline_count', 'downline_earnings', 'referral_source',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'total_earned' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(CommissionSettlement::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(CommissionPayout::class);
    }

    public function referralLinks(): HasMany
    {
        return $this->hasMany(ReferralLink::class);
    }

    public function subscriptions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Subscription::class,
            SubscriptionAgent::class,
            'agent_id',
            'id',
            'id',
            'subscription_id'
        );
    }

    public function parentAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'parent_agent_id');
    }

    public function childAgents(): HasMany
    {
        return $this->hasMany(Agent::class, 'parent_agent_id');
    }

    public function affiliateClicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class, 'agent_id');
    }

    public function monthlySnapshots(): HasMany
    {
        return $this->hasMany(AgentMonthlySnapshot::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * 可提现余额
     */
    public function getAvailableBalanceAttribute(): float
    {
        return $this->total_earned - $this->total_withdrawn;
    }

    /**
     * 根据等级获取默认佣金比例
     */
    public function getEffectiveRate(): float
    {
        if ($this->commission_rate !== null) {
            return $this->commission_rate;
        }
        return match ($this->level) {
            'silver' => 10.0,
            'gold' => 20.0,
            'platinum' => 30.0,
            default => 5.0,
        };
    }
}
