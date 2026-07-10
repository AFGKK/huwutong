<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperPreSaleOrder
 */
class PreSaleOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'campaign_id', 'tenant_id', 'customer_id', 'user_id',
        'order_no', 'tier_index', 'tier_name',
        'total_amount', 'deposit_paid', 'final_payment', 'final_paid',
        'payment_status', 'payment_method', 'payment_meta', 'fulfillment_status',
        'quantity', 'deposit_paid_at', 'final_paid_at', 'fulfilled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'deposit_paid' => 'decimal:2',
            'final_payment' => 'decimal:2',
            'final_paid' => 'decimal:2',
            'quantity' => 'integer',
            'deposit_paid_at' => 'datetime',
            'final_paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'payment_meta' => 'array',
        ];
    }

    const PAYMENT_STATUSES = [
        'deposit_pending', 'deposit_paid', 'final_pending',
        'final_paid', 'refunding', 'refunded',
    ];

    const FULFILLMENT_STATUSES = ['pending', 'processing', 'shipped', 'delivered'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PreSaleCampaign::class, 'campaign_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
