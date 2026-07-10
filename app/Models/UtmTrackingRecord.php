<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperUtmTrackingRecord
 */
class UtmTrackingRecord extends Model
{
    protected $fillable = [
        'trackable_type',
        'trackable_id',
        'session_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'landing_page',
        'referrer_url',
        'ip_address',
        'user_agent',
        'channel_group',
        'attribution_type',
    ];

    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeByChannel($query, ?string $channelGroup = null)
    {
        if ($channelGroup) {
            return $query->where('channel_group', $channelGroup);
        }
        return $query;
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }
}
