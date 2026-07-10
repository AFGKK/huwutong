<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSlaProbeUptime
 */
class SlaProbeUptime extends Model
{
    protected $fillable = [
        'sla_probe_id',
        'tenant_id',
        'record_date',
        'period',
        'total_checks',
        'success_checks',
        'failure_checks',
        'uptime_percentage',
        'avg_response_time_ms',
        'max_response_time_ms',
        'min_response_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'uptime_percentage' => 'decimal:2',
            'total_checks' => 'integer',
            'success_checks' => 'integer',
            'failure_checks' => 'integer',
        ];
    }

    public function probe(): BelongsTo
    {
        return $this->belongsTo(SlaProbe::class, 'sla_probe_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
