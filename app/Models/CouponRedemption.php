<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    protected $fillable = [
        'coupon_id', 'subscription_id', 'invoice_id', 'order_id', 'customer_id',
        'discount_amount', 'currency', 'original_amount', 'final_amount',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
