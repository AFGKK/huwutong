<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPricingTier
 */
class PricingTier extends Model
{
    use HasFactory;

    protected $table = 'pricing_tiers';

    protected $fillable = [
        'pricing_plan_id',
        'name',
        'from_quantity',
        'to_quantity',
        'unit_price',
        'flat_fee',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'from_quantity' => 'integer',
            'to_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'flat_fee' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }
}
