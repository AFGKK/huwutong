<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 流失预警预测
 */
class ChurnPrediction extends Model
{
    const RISK_LOW = 'low';
    const RISK_MEDIUM = 'medium';
    const RISK_HIGH = 'high';
    const RISK_CRITICAL = 'critical';

    protected $fillable = [
        'tenant_id', 'customer_id',
        'churn_probability', 'risk_level',
        'top_signals', 'recommendations',
        'predicted_at',
    ];

    protected function casts(): array
    {
        return [
            'churn_probability' => 'decimal:4',
            'top_signals' => 'array',
            'recommendations' => 'array',
            'predicted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
