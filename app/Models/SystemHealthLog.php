<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperSystemHealthLog
 */
class SystemHealthLog extends Model
{
    protected $table = 'system_health_logs';

    protected $fillable = [
        'status', 'overall_score',
        'db_latency_ms', 'db_healthy',
        'redis_latency_ms', 'redis_healthy',
        'cache_healthy', 'cache_driver',
        'queue_healthy', 'queue_connection', 'queue_size',
        'memory_mb', 'peak_memory_mb',
        'disk_usage_percent', 'disk_free_gb',
        'db_connections', 'failed_jobs_count',
        'circuit_breakers', 'extra_metrics',
        'snapped_at',
    ];

    protected function casts(): array
    {
        return [
            'overall_score' => 'decimal:2',
            'db_latency_ms' => 'decimal:2',
            'redis_latency_ms' => 'decimal:2',
            'memory_mb' => 'decimal:2',
            'peak_memory_mb' => 'decimal:2',
            'disk_usage_percent' => 'decimal:2',
            'disk_free_gb' => 'decimal:2',
            'db_healthy' => 'boolean',
            'redis_healthy' => 'boolean',
            'cache_healthy' => 'boolean',
            'queue_healthy' => 'boolean',
            'circuit_breakers' => 'array',
            'extra_metrics' => 'array',
            'snapped_at' => 'datetime',
        ];
    }
}
