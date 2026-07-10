<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @mixin IdeHelperSlaBreach
 */
class SlaBreach extends Model
{
    use HasFactory;

    protected $table = 'sla_breaches';

    protected $fillable = [
        'sla_contract_id', 'sla_metric_id', 'breach_type', 'severity',
        'breachable_type', 'breachable_id',
        'description', 'expected_value', 'actual_value', 'deviation',
        'context', 'status',
        'acknowledged_at', 'resolved_at', 'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    const SEVERITIES = ['minor', 'major', 'critical'];
    const STATUSES = ['open', 'acknowledged', 'resolved', 'escalated'];

    public function contract(): BelongsTo { return $this->belongsTo(SlaContract::class, 'sla_contract_id'); }
    public function metric(): BelongsTo { return $this->belongsTo(SlaMetric::class, 'sla_metric_id'); }
    public function breachable(): \Illuminate\Database\Eloquent\Relations\MorphTo { return $this->morphTo(); }
    public function compensation(): HasOne { return $this->hasOne(SlaCompensation::class, 'sla_breach_id'); }
}
