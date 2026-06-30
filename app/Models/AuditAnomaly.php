<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditAnomaly extends Model
{
    protected $table = 'audit_anomalies';

    protected $fillable = [
        'tenant_id', 'anomaly_type', 'severity', 'metric',
        'baseline_value', 'actual_value', 'deviation',
        'description', 'context', 'status',
        'detected_at', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    const TYPES = ['spike', 'drop', 'pattern_change', 'unusual_hours', 'geo_anomaly'];
    const SEVERITIES = ['info', 'warning', 'critical'];
    const STATUSES = ['open', 'acknowledged', 'resolved', 'dismissed'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
