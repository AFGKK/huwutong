<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundlePurchase extends Model
{
    protected $fillable = [
        'product_bundle_id', 'tenant_id', 'customer_id', 'invoice_id',
        'order_no', 'paid_amount', 'currency', 'status',
        'purchased_items', 'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_amount' => 'decimal:2',
            'purchased_items' => 'array',
            'purchased_at' => 'datetime',
        ];
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'product_bundle_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
