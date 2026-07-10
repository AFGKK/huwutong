<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperMarketplacePushCampaign
 */
class MarketplacePushCampaign extends Model
{
    protected $fillable = [
        'title', 'content', 'type', 'target_type',
        'target_app_id', 'target_category',
        'link_type', 'link_value', 'metadata',
        'status', 'target_count', 'sent_count', 'read_count',
        'scheduled_at', 'sent_at', 'completed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'json',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public const TYPES = ['marketing', 'update', 'promo', 'info'];
    public const TARGET_TYPES = ['all', 'installed_app', 'category', 'specific_app'];
    public const STATUSES = ['draft', 'scheduled', 'sending', 'sent', 'cancelled'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(MarketplacePushDelivery::class, 'campaign_id');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }
}
