<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCallback extends Model
{
    protected $fillable = [
        'order_id', 'gateway', 'event_type', 'transaction_id',
        'merchant_order_no', 'amount', 'currency', 'status',
        'raw_payload', 'response', 'error_message',
        'idempotency_key', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'raw_payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeByGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeByEvent($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['received', 'processing']);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
