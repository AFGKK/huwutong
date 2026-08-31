<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperOrder
 */
class Order extends Model
{
    use SoftDeletes;

    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDING = 'refunding';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_PARTIAL_REFUND = 'partial_refund';

    private const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_PAID, self::STATUS_CANCELLED],
        self::STATUS_PAID => [self::STATUS_REFUNDING],
        self::STATUS_REFUNDING => [self::STATUS_PAID, self::STATUS_REFUNDED, self::STATUS_PARTIAL_REFUND],
        self::STATUS_CANCELLED => [],
        self::STATUS_REFUNDED => [],
        self::STATUS_PARTIAL_REFUND => [],
    ];

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function transitionTo(string $to): void
    {
        if (!$this->canTransitionTo($to)) {
            throw new \RuntimeException(__('app.common.order_status_transition_not_allowed', ['from' => $this->status, 'to' => $to]));
        }

        $this->update(['status' => $to]);
    }

    protected $fillable = [
        'order_no', 'tenant_id', 'user_id', 'customer_id',
        'total_amount', 'discount_amount', 'final_amount', 'currency',
        'status', 'payment_method', 'transaction_id',
        'coupon_info', 'billing_address', 'notes',
        'paid_at', 'cancelled_at', 'expires_at', 'payment_extra',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'coupon_info' => 'array',
            'billing_address' => 'array',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
            'payment_extra' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
