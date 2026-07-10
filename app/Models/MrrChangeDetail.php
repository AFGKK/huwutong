<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperMrrChangeDetail
 */
class MrrChangeDetail extends Model
{
    protected $table = 'mrr_change_details';

    protected $fillable = [
        'tenant_id', 'year_month', 'change_type',
        'subscription_id', 'customer_id', 'plan_id',
        'previous_mrr', 'new_mrr', 'mrr_impact',
        'currency', 'reason', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_mrr' => 'decimal:2',
            'new_mrr' => 'decimal:2',
            'mrr_impact' => 'decimal:2',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan()
    {
        return $this->belongsTo(PricingPlan::class, 'plan_id');
    }
}
