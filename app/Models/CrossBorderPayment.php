<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCrossBorderPayment
 */
class CrossBorderPayment extends Model
{
    use HasFactory;

    protected $table = 'cross_border_payments';

    protected $fillable = [
        'tenant_id', 'customer_id', 'invoice_id',
        'currency', 'amount', 'amount_cny', 'exchange_rate',
        'payment_gateway', 'gateway_transaction_id',
        'customer_country', 'merchant_country',
        'gateway_fee', 'gateway_fee_cny',
        'status', 'transaction_type',
        'gateway_response', 'compliance_info',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:6',
            'gateway_fee' => 'decimal:4',
            'gateway_fee_cny' => 'decimal:4',
            'gateway_response' => 'array',
            'compliance_info' => 'array',
            'settled_at' => 'datetime',
        ];
    }

    const STATUSES = ['pending', 'completed', 'failed', 'refunded'];
    const TYPES = ['payment', 'refund', 'chargeback'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
