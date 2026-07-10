<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPlanUpgradePath
 */
class PlanUpgradePath extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_plan_id', 'to_plan_id',
        'proration_ratio', 'additional_fee',
        'allow_downgrade', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'proration_ratio' => 'decimal:4',
            'additional_fee' => 'decimal:2',
            'allow_downgrade' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'from_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'to_plan_id');
    }
}
