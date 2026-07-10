<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperOemSubscription
 */
class OemSubscription extends Model
{
    use SoftDeletes;

    protected $table = 'oem_subscriptions';

    protected $fillable = [
        'tenant_id', 'tier', 'billing_period', 'price',
        'active_features', 'starts_at', 'expires_at', 'trial_ends_at',
        'is_active', 'is_trial', 'status',
        'max_domains', 'max_themes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'active_features' => 'array',
            'metadata' => 'array',
            'price' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'is_active' => 'boolean',
            'is_trial' => 'boolean',
            'max_domains' => 'integer',
            'max_themes' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function changes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OemSubscriptionChange::class, 'oem_subscription_id');
    }

    /**
     * 检查是否拥有某功能
     */
    public function hasFeature(string $feature): bool
    {
        if (!$this->is_active || !$this->active_features) {
            return false;
        }
        return in_array($feature, $this->active_features);
    }

    /**
     * 检查套餐是否有效
     */
    public function isValid(): bool
    {
        return $this->is_active
            && $this->status === 'active'
            && (!$this->expires_at || $this->expires_at->isFuture());
    }
}
