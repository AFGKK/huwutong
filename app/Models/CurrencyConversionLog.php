<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCurrencyConversionLog
 */
class CurrencyConversionLog extends Model
{
    use HasFactory;

    protected $table = 'currency_conversion_logs';

    protected $fillable = [
        'tenant_id', 'customer_id', 'invoice_id',
        'from_currency', 'to_currency', 'from_amount', 'to_amount',
        'rate_used', 'rate_markup', 'conversion_type', 'source', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'rate_used' => 'decimal:6',
            'rate_markup' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
