<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 联盟推广素材 (M3-05)
 *
 * @mixin IdeHelperAffiliateCreative
 */
class AffiliateCreative extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'type', 'name', 'url', 'content',
        'image_url', 'utm_params', 'click_count', 'conversion_count', 'is_active',
        'commission_amount', 'commission_rate',
        'status', 'created_by', 'reviewed_at', 'review_notes',
    ];

    protected $casts = [
        'utm_params' => 'array',
        'is_active' => 'boolean',
        'commission_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(AffiliateCampaign::class, 'campaign_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function clickLogs()
    {
        return $this->hasMany(AffiliateClick::class, 'creative_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
