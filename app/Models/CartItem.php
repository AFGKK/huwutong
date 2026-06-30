<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id', 'sku_id', 'quantity',
        'unit_price', 'original_price', 'subtotal',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'unit_price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'sku_id');
    }
}
