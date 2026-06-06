<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SDK 心跳记录
 *
 * SDK 客户端定期上报健康状态、版本信息、运行环境。
 */
class SdkHeartbeat extends Model
{
    protected $fillable = [
        'license_id', 'device_id', 'tenant_id',
        'sdk_version', 'sdk_language', 'sdk_platform', 'sdk_arch',
        'hostname', 'ip_address', 'uptime_seconds',
        'runtime_version', 'health_status', 'features_active',
        'metrics', 'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'health_status' => 'array',
            'features_active' => 'array',
            'metrics' => 'array',
            'reported_at' => 'datetime',
            'uptime_seconds' => 'integer',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
