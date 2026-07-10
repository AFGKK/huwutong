<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 推广点击/转化追踪 (M3-05)
 *
 * @mixin IdeHelperAffiliateClick
 */
class AffiliateClick extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id', 'campaign_id', 'creative_id',
        'referral_code', 'ip_address', 'user_agent',
        'referrer_url', 'landing_url', 'utm_params',
        'converted', 'converted_at', 'converted_user_id', 'commission_amount', 'commission_rate', 'platform_share_rate',
    ];

    protected $casts = [
        'utm_params' => 'array',
        'converted' => 'boolean',
        'commission_amount' => 'decimal:2',
        'converted_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function campaign()
    {
        return $this->belongsTo(AffiliateCampaign::class, 'campaign_id');
    }

    public function creative()
    {
        return $this->belongsTo(AffiliateCreative::class, 'creative_id');
    }

    public function convertedUser()
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }
}
