<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperInventoryLog
 */
class InventoryLog extends Model
{
    protected $fillable = [
        'sku_id', 'type', 'quantity', 'stock_before', 'stock_after',
        'reference_type', 'reference_id', 'remark', 'operator_id',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'sku_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
