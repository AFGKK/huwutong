<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SDK Telemetry 事件日志（脱敏后使用统计）
 */
class SdkTelemetryEvent extends Model
{
    protected $fillable = [
        'license_id', 'tenant_id', 'event_type', 'event_name',
        'event_data', 'count', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
            'count' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
