<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperResaleTransaction
 */
class ResaleTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'listing_id', 'buyer_customer_id', 'reference',
        'agreed_price', 'commission_amount', 'seller_payout', 'currency',
        'status', 'payment_method', 'payment_reference',
        'paid_at', 'confirmed_by_seller', 'seller_confirmed_at',
        'executed_by', 'executed_at', 'audit_log',
    ];

    protected function casts(): array
    {
        return [
            'agreed_price' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'seller_payout' => 'decimal:2',
            'paid_at' => 'datetime',
            'seller_confirmed_at' => 'datetime',
            'executed_at' => 'datetime',
            'audit_log' => 'array',
        ];
    }

    // ─── 状态常量 ───
    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_PAID = 'paid';
    const STATUS_PENDING_TRANSFER = 'pending_transfer';
    const STATUS_COMPLETED = 'completed';
    const STATUS_DISPUTED = 'disputed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ResaleListing::class, 'listing_id');
    }

    public function buyerCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'buyer_customer_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_seller');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
