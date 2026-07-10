<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperInvoiceTaxLine
 */
class InvoiceTaxLine extends Model
{
    protected $fillable = [
        'invoice_id', 'tax_rate_id',
        'name', 'rate', 'taxable_amount', 'tax_amount',
        'exempt_reason',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
