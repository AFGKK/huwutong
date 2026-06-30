<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BundleItem extends Model
{
    protected $fillable = [
        'product_bundle_id', 'itemable_type', 'itemable_id',
        'name', 'original_price', 'discount_percent', 'quantity',
        'type', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'product_bundle_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getEffectivePriceAttribute(): float
    {
        $price = $this->original_price;
        if ($this->discount_percent > 0) {
            $price = $price * (1 - $this->discount_percent / 100);
        }
        return round($price * $this->quantity, 2);
    }
}
