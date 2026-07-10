<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperProductSku
 */
class ProductSku extends Model
{
    protected $fillable = [
        'product_id', 'sku_code', 'name', 'image_url', 'specs',
        'price', 'compare_at_price', 'currency',
        'stock', 'sold_count', 'is_active', 'billing_cycle',
        'commission_rate',
        'deliverables',
        'low_stock_threshold',
        'allow_backorder',
        'weight', 'length', 'width', 'height',
    ];

    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'deliverables' => 'array',
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock' => 'integer',
            'sold_count' => 'integer',
            'is_active' => 'boolean',
            'commission_rate' => 'decimal:2',
            'low_stock_threshold' => 'integer',
            'allow_backorder' => 'boolean',
            'weight' => 'decimal:2',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function currencyPrices(): HasMany
    {
        return $this->hasMany(SkuCurrencyPrice::class, 'product_sku_id');
    }

    public function stockLogs(): HasMany
    {
        return $this->hasMany(SkuStockLog::class, 'product_sku_id');
    }
}
