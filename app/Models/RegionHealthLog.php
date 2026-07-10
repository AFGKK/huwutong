<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperRegionHealthLog
 */
class RegionHealthLog extends Model
{
    use HasFactory;

    protected $table = 'region_health_logs';

    protected $fillable = [
        'region_key',
        'data_center_id',
        'latency_ms', 'response_time_ms', 'load', 'is_healthy', 'check_type',
        'checker_region', 'error_message', 'metrics', 'details', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'latency_ms' => 'decimal:2',
            'load' => 'decimal:2',
            'is_healthy' => 'boolean',
            'metrics' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    const CHECK_TYPES = ['ping', 'http', 'dns'];

    public function dataCenter(): BelongsTo
    {
        return $this->belongsTo(DataCenter::class);
    }
}
