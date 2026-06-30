<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 代理商等级晋升规则 (M3-04)
 */
class AgentTierRule extends Model
{
    protected $fillable = [
        'from_level', 'to_level',
        'min_days', 'min_subscriptions', 'min_total_amount',
        'min_referrals', 'min_monthly_amount',
        'period', 'description', 'is_active',
    ];

    protected $casts = [
        'min_total_amount' => 'decimal:2',
        'min_monthly_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
