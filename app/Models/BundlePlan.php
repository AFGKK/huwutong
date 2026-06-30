<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundlePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_plan_id', 'included_plan_id', 'type',
        'discount_percent', 'fixed_discount', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'fixed_discount' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parentPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'parent_plan_id');
    }

    public function includedPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class, 'included_plan_id');
    }
}
