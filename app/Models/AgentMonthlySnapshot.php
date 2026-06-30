<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 代理商月度业绩快照
 */
class AgentMonthlySnapshot extends Model
{
    protected $fillable = [
        'agent_id', 'year_month', 'revenue', 'commission_earned',
        'new_subscriptions', 'new_referrals', 'new_downline', 'conversion_rate',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'commission_earned' => 'decimal:2',
            'new_subscriptions' => 'integer',
            'new_referrals' => 'integer',
            'new_downline' => 'integer',
            'conversion_rate' => 'decimal:2',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
