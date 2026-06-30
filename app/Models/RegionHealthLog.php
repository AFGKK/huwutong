<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionHealthLog extends Model
{
    use HasFactory;

    protected $table = 'region_health_logs';

    protected $fillable = [
        'data_center_id',
        'latency_ms', 'load', 'is_healthy', 'check_type',
        'error_message', 'metrics', 'checked_at',
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
