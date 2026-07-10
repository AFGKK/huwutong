<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCreditLimit
 */
class CreditLimit extends Model
{
    protected $table = 'credit_limits';

    protected $fillable = [
        'tenant_id', 'customer_id',
        'credit_limit', 'used_credit', 'grace_days',
        'status', 'last_assessment_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'used_credit' => 'decimal:2',
            'metadata' => 'array',
            'last_assessment_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function getAvailableCreditAttribute(): float
    {
        return max(0, (float) $this->credit_limit - (float) $this->used_credit);
    }
}
