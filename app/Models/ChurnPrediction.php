<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperChurnPrediction
 */
class ChurnPrediction extends Model
{
    protected $fillable = [
        'customer_id', 'tenant_id',
        'churn_score', 'churn_risk',
        'churn_probability', 'risk_level',
        'top_signals', 'recommendations',
        'predicted_churn_date',
        'signals', 'recommended_action',
        'predicted_at',
    ];

    protected function casts(): array
    {
        return [
            'churn_score' => 'integer',
            'churn_probability' => 'decimal:4',
            'signals' => 'array',
            'top_signals' => 'array',
            'recommendations' => 'array',
            'predicted_churn_date' => 'date',
            'predicted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
