<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCloudMarketplaceMetering
 */
class CloudMarketplaceMetering extends Model
{
    protected $table = 'cloud_marketplace_metering';

    protected $fillable = [
        'tenant_id', 'subscription_id', 'marketplace',
        'dimension', 'quantity', 'metered_at',
        'reported_at', 'status', 'error_message', 'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'metered_at' => 'datetime',
            'reported_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CloudMarketplaceSubscription::class, 'subscription_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
