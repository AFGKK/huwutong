<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperRenewalAttempt
 */
class RenewalAttempt extends Model
{
    protected $fillable = [
        'subscription_id', 'invoice_id', 'attempt_number',
        'payment_method', 'amount', 'currency',
        'status', 'failure_reason', 'failure_detail',
        'transaction_id', 'retry_plan', 'escalated',
        'attempted_at', 'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'attempted_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'retry_plan' => 'array',
            'escalated' => 'boolean',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
