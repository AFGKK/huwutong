<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingExperimentParticipant extends Model
{
    protected $table = 'pricing_experiment_participants';

    protected $fillable = [
        'experiment_id', 'customer_id', 'subscription_id',
        'group', 'original_price', 'experiment_price',
        'revenue_impact', 'behavior_data', 'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'experiment_price' => 'decimal:2',
            'revenue_impact' => 'decimal:2',
            'behavior_data' => 'array',
            'assigned_at' => 'datetime',
        ];
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(PricingExperiment::class, 'experiment_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
