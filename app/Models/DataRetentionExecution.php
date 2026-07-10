<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperDataRetentionExecution
 */
class DataRetentionExecution extends Model
{
    protected $fillable = [
        'policy_key', 'table_name', 'action', 'total_records', 'affected_records',
        'batch_count', 'is_dry_run', 'status', 'error_message',
        'duration_ms', 'details', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'total_records' => 'integer',
        'affected_records' => 'integer',
        'batch_count' => 'integer',
        'is_dry_run' => 'boolean',
        'duration_ms' => 'integer',
        'details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUSES = ['pending', 'running', 'completed', 'failed'];

    public function scopeByPolicy($query, string $policyKey)
    {
        return $query->where('policy_key', $policyKey);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
