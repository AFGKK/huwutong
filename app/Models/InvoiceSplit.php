<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperInvoiceSplit
 */
class InvoiceSplit extends Model
{
    use HasFactory;

    protected $table = 'invoice_splits';

    protected $fillable = [
        'tenant_id', 'original_invoice_id', 'split_invoice_id',
        'amount', 'reason', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'original_invoice_id');
    }

    public function splitInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'split_invoice_id');
    }
}
