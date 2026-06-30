<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrepaidTransaction extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'invoice_id',
        'type', 'amount', 'balance_before', 'balance_after',
        'currency', 'payment_method', 'gateway_transaction_id',
        'status', 'description', 'metadata', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
            'completed_at' => 'datetime',
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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
