<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperInvoiceReconciliation
 */
class InvoiceReconciliation extends Model
{
    use HasFactory;

    protected $table = 'invoice_reconciliations';

    protected $fillable = [
        'tenant_id', 'invoice_id', 'customer_id',
        'reconciliation_type', 'status',
        'invoice_amount', 'actual_amount', 'difference', 'currency',
        'payment_ref', 'payment_date', 'notes', 'evidence',
        'matched_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'payment_date' => 'datetime',
            'matched_at' => 'datetime',
            'resolved_at' => 'datetime',
            'invoice_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    const STATUSES = ['pending', 'matched', 'unmatched', 'resolved'];
    const STATUS_LABELS = [
        'pending' => '待对账',
        'matched' => '已匹配',
        'unmatched' => '不匹配',
        'resolved' => '已解决',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
