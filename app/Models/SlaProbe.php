<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SlaProbe extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'method',
        'headers',
        'body',
        'expected_status',
        'expected_body_contains',
        'timeout_seconds',
        'interval_minutes',
        'sla_targets',
        'is_active',
        'last_status',
        'last_response_time_ms',
        'last_probed_at',
        'consecutive_failures',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'sla_targets' => 'array',
            'is_active' => 'boolean',
            'last_probed_at' => 'datetime',
            'timeout_seconds' => 'integer',
            'interval_minutes' => 'integer',
            'consecutive_failures' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(SlaProbeResult::class, 'sla_probe_id');
    }

    public function uptimeRecords(): HasMany
    {
        return $this->hasMany(SlaProbeUptime::class, 'sla_probe_id');
    }

    /**
     * 检查是否应该进行拨测（基于间隔时间）
     */
    public function shouldProbe(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        if (!$this->last_probed_at) {
            return true;
        }
        return $this->last_probed_at->addMinutes($this->interval_minutes)->isPast();
    }

    /**
     * 检查HTTP状态码是否符合预期范围（如 "200-299"）
     */
    public function isExpectedStatus(int $statusCode): bool
    {
        $range = explode('-', $this->expected_status ?? '200-299');
        $min = (int) ($range[0] ?? 200);
        $max = (int) ($range[1] ?? 299);
        return $statusCode >= $min && $statusCode <= $max;
    }

    /**
     * 判断拨测状态是否健康
     */
    public function isHealthy(): bool
    {
        return $this->last_status === 'up';
    }
}
