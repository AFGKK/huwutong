<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperReconciliationChannelRow
 */
class ReconciliationChannelRow extends Model
{
    protected $fillable = [
        'import_id', 'reconciliation_id', 'channel', 'transaction_id', 'order_id',
        'amount', 'fee', 'currency', 'status', 'transaction_time',
        'payer_account', 'payee_account', 'raw_data',
        'match_status', 'matched_order_id', 'matched_order_no', 'difference', 'notes',
    ];

    protected $casts = [
        'transaction_time' => 'datetime',
        'raw_data' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(ReconciliationImport::class, 'import_id');
    }
}
