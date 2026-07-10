<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperCloudMarketplaceSubscription
 */
class CloudMarketplaceSubscription extends Model
{
    protected $table = 'cloud_marketplace_subscriptions';

    protected $fillable = [
        'tenant_id', 'marketplace', 'marketplace_subscription_id',
        'offer_id', 'customer_id', 'customer_name', 'customer_email',
        'local_customer_id', 'local_user_id', 'local_subscription_id',
        'status', 'tier', 'fulfillment_data',
        'subscribed_at', 'activated_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'fulfillment_data' => 'array',
            'subscribed_at' => 'datetime',
            'activated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CloudMarketplaceProduct::class, 'offer_id', 'offer_id')
            ->where('marketplace', $this->marketplace);
    }

    public function localCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'local_customer_id');
    }

    public function localUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'local_user_id');
    }

    public function localSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'local_subscription_id');
    }

    public function metering(): HasMany
    {
        return $this->hasMany(CloudMarketplaceMetering::class, 'subscription_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['subscribed', 'active']);
    }
}
