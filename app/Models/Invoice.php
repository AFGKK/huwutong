<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'customer_id', 'subscription_id',
        'invoice_no', 'amount', 'subtotal', 'discount_amount',
        'coupon_code', 'coupon_id',
        'currency', 'status', 'paid',
        'payment_method', 'billing_reason', 'paid_at', 'due_at',
        'billing_country', 'billing_region', 'billing_city', 'billing_zip',
        'billing_address_line1', 'billing_address_line2',
        'tax_type', 'tax_rate_applied', 'tax_amount',
        'tax_exempt_certificate_id', 'tax_exempt_reason', 'tax_reporting_code',
        'invoice_pdf_url', 'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'tax_rate_applied' => 'decimal:4',
            'paid' => 'boolean',
            'paid_at' => 'datetime',
            'due_at' => 'datetime',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function taxLines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InvoiceTaxLine::class);
    }

    public function revenueSchedule(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\RevenueRecognitionSchedule::class, 'invoice_id');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    /**
     * 退款记录
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'invoice_id');
    }

    /**
     * 对账记录
     */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(\App\Models\InvoiceReconciliation::class, 'invoice_id');
    }

    /**
     * 拆分记录（原始发票）
     */
    public function splits(): HasMany
    {
        return $this->hasMany(\App\Models\InvoiceSplit::class, 'original_invoice_id');
    }

    /**
     * 拆分记录（目标发票）
     */
    public function splitFrom(): HasMany
    {
        return $this->hasMany(\App\Models\InvoiceSplit::class, 'split_invoice_id');
    }
}
