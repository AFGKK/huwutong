<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkuCurrencyPrice extends Model
{
    protected $fillable = [
        'product_sku_id',
        'currency',
        'price',
        'compare_at_price',
        'cost_price',
        'is_converted',
        'source_currency',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'is_converted' => 'boolean',
        ];
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }
}
