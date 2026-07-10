<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperInvoiceLineItem
 */
class InvoiceLineItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'invoice_id', 'tenant_id',
        'type', 'description', 'metric_key',
        'quantity', 'unit_price', 'amount', 'currency',
        'breakdown', 'period_start', 'period_end', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:2',
            'breakdown' => 'array',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
