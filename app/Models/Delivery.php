<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperDelivery
 */
class Delivery extends Model
{
    protected $fillable = [
        'order_id', 'order_item_id', 'delivery_type',
        'content', 'delivery_channel', 'status',
        'error_message', 'meta', 'sent_at', 'delivered_at',
        'webhook_pushed', 'email_sent', 'api_callback_sent',
        'webhook_pushed_at', 'email_sent_at', 'api_callback_sent_at',
        'auto_license_id',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'webhook_pushed' => 'boolean',
            'email_sent' => 'boolean',
            'api_callback_sent' => 'boolean',
            'webhook_pushed_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'api_callback_sent_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeliveryLog::class);
    }
}
