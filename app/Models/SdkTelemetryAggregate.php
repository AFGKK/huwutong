<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SDK Telemetry 聚合统计
 */
class SdkTelemetryAggregate extends Model
{
    protected $fillable = [
        'tenant_id', 'metric_key', 'dimension', 'dimension_value',
        'count', 'agg_date',
    ];

    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'agg_date' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
