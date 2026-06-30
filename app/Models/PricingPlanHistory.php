<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingPlanHistory extends Model
{
    protected $fillable = [
        'pricing_plan_id',
        'old_price_monthly', 'new_price_monthly',
        'old_price_yearly', 'new_price_yearly',
        'changed_by', 'reason', 'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'old_price_monthly' => 'decimal:2',
            'new_price_monthly' => 'decimal:2',
            'old_price_yearly' => 'decimal:2',
            'new_price_yearly' => 'decimal:2',
            'effective_from' => 'datetime',
        ];
    }

    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }
}
