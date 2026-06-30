<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionRedemption extends Model
{
    protected $fillable = [
        'promotion_id', 'customer_id', 'invoice_id',
        'promotion_type', 'discount_amount', 'currency',
        'context', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'context' => 'array',
        ];
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
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
