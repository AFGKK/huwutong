<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 联盟推广素材 (M3-05)
 */
class AffiliateCreative extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'type', 'name', 'url', 'content',
        'image_url', 'utm_params', 'click_count', 'conversion_count', 'is_active',
    ];

    protected $casts = [
        'utm_params' => 'array',
        'is_active' => 'boolean',
    ];

    public function campaign()
    {
        return $this->belongsTo(AffiliateCampaign::class, 'campaign_id');
    }

    public function clickLogs()
    {
        return $this->hasMany(AffiliateClick::class, 'creative_id');
    }
}
