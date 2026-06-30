<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoRenewalAttempt extends Model
{
    protected $table = 'auto_renewal_attempts';

    protected $fillable = [
        'auto_renewal_subscription_id', 'attempt_type', 'amount',
        'currency', 'status', 'failure_reason', 'result_data',
    ];

    protected function casts(): array
    {
        return [
            'result_data' => 'array',
            'amount' => 'decimal:2',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(AutoRenewalSubscription::class, 'auto_renewal_subscription_id');
    }
}
