<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 联盟推广活动 (M3-05)
 *
 * @mixin IdeHelperAffiliateCampaign
 */
class AffiliateCampaign extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';

    const TYPE_REFERRAL = 'referral';
    const TYPE_COMMISSION = 'commission';
    const TYPE_REWARD = 'reward';
    const TYPE_REBATE = 'rebate';

    protected $fillable = [
        'name', 'slug', 'description', 'status', 'type',
        'starts_at', 'ends_at',
        'target_audience', 'reward_rules',
        'reward_first', 'reward_renewal', 'reward_upgrade',
        'budget_total', 'budget_deposited', 'budget_used',
        'billing_mode', 'cost_per_click', 'cost_per_impression',
        'platform_share_rate',
        'max_participants', 'participant_count', 'conversion_count',
        'terms', 'created_by',
    ];

    protected $casts = [
        'target_audience' => 'array',
        'reward_rules' => 'array',
        'reward_first' => 'decimal:2',
        'reward_renewal' => 'decimal:2',
        'reward_upgrade' => 'decimal:2',
        'budget_total' => 'decimal:2',
        'budget_deposited' => 'decimal:2',
        'budget_used' => 'decimal:2',
        'cost_per_click' => 'decimal:2',
        'cost_per_impression' => 'decimal:2',
        'platform_share_rate' => 'decimal:2',
        'terms' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creatives()
    {
        return $this->hasMany(AffiliateCreative::class, 'campaign_id');
    }

    public function clicks()
    {
        return $this->hasMany(AffiliateClick::class, 'campaign_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) return false;
        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) return false;
        if ($this->ends_at && $now->gt($this->ends_at)) return false;
        return true;
    }
}
