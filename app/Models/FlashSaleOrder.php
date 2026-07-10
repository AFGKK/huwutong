<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperFlashSaleOrder
 */
class FlashSaleOrder extends Model
{
    protected $table = 'flash_sale_orders';

    protected $fillable = [
        'flash_sale_id', 'customer_id', 'order_id', 'queue_token',
        'status', 'device_fingerprint', 'ip_address',
        'reserved_at', 'paid_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function flashSale(): BelongsTo { return $this->belongsTo(FlashSale::class, 'flash_sale_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
