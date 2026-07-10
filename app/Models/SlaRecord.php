<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSlaRecord
 */
class SlaRecord extends Model
{
    protected $table = 'sla_records';

    protected $fillable = [
        'sla_contract_id', 'sla_metric_id', 'record_date', 'period',
        'actual_value', 'target_value', 'compliance_rate',
        'status', 'is_breached', 'details',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'is_breached' => 'boolean',
            'details' => 'array',
        ];
    }

    public function contract(): BelongsTo { return $this->belongsTo(SlaContract::class, 'sla_contract_id'); }
    public function metric(): BelongsTo { return $this->belongsTo(SlaMetric::class, 'sla_metric_id'); }
}
