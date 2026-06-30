<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkuStockLog extends Model
{
    protected $fillable = [
        'product_sku_id', 'user_id', 'change', 'old_stock', 'new_stock',
        'reason', 'reference_type', 'reference_id',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
