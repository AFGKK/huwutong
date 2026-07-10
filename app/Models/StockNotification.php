<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperStockNotification
 */
class StockNotification extends Model
{
    protected $fillable = [
        'product_sku_id',
        'user_id',
        'email',
        'phone',
        'notified',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'notified' => 'boolean',
            'notified_at' => 'datetime',
        ];
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
