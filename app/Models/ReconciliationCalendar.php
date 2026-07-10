<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperReconciliationCalendar
 */
class ReconciliationCalendar extends Model
{
    protected $fillable = [
        'tenant_id', 'period_type', 'period_start', 'period_end',
        'status', 'total_transactions', 'matched_count', 'unmatched_count',
        'total_amount', 'difference_amount', 'reconciled_at', 'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'reconciled_at' => 'datetime',
    ];
}
