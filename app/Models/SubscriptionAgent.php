<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 订阅-代理关联
 *
 * 记录哪个代理推荐了哪个订阅。
 *
 * @mixin IdeHelperSubscriptionAgent
 */
class SubscriptionAgent extends Model
{
    protected $fillable = [
        'subscription_id', 'agent_id', 'commission_plan_id',
        'referral_code', 'attribution_source', 'attributed_at',
    ];

    protected function casts(): array
    {
        return [
            'attributed_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CommissionPlan::class, 'commission_plan_id');
    }
}
