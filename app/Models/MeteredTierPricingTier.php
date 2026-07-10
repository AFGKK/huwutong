<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperMeteredTierPricingTier
 */
class MeteredTierPricingTier extends Model
{
    protected $table = 'metered_tier_pricing_tiers';

    protected $fillable = [
        'tiered_pricing_id', 'from_unit', 'to_unit',
        'unit_price', 'price_model', 'flat_fee',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'flat_fee' => 'decimal:2',
        ];
    }

    public function pricing(): BelongsTo { return $this->belongsTo(MeteredTieredPricing::class, 'tiered_pricing_id'); }
}
