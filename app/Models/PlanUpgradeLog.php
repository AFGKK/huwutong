<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperPlanUpgradeLog
 */
class PlanUpgradeLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'subscription_id', 'from_plan_id', 'to_plan_id', 'type',
        'original_price', 'new_price', 'credit', 'charge', 'discount',
        'status', 'details', 'operator_id', 'notes', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'credit' => 'decimal:2',
            'charge' => 'decimal:2',
            'discount' => 'decimal:2',
            'details' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'from_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'to_plan_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
