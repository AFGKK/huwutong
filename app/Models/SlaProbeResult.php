<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSlaProbeResult
 */
class SlaProbeResult extends Model
{
    protected $fillable = [
        'sla_probe_id',
        'tenant_id',
        'status',
        'response_time_ms',
        'http_status_code',
        'error_message',
        'response_headers',
        'response_size_bytes',
        'probed_at',
    ];

    protected function casts(): array
    {
        return [
            'response_headers' => 'array',
            'probed_at' => 'datetime',
            'response_time_ms' => 'integer',
            'http_status_code' => 'integer',
            'response_size_bytes' => 'integer',
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
