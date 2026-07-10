<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperCloudMarketplaceProduct
 */
class CloudMarketplaceProduct extends Model
{
    protected $table = 'cloud_marketplace_products';

    protected $fillable = [
        'tenant_id', 'marketplace', 'offer_id', 'offer_name',
        'status', 'mapping_rules', 'description',
    ];

    protected function casts(): array
    {
        return [
            'mapping_rules' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CloudMarketplaceSubscription::class, 'offer_id', 'offer_id')
            ->where('marketplace', $this->marketplace);
    }
}
