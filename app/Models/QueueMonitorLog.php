<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperQueueMonitorLog
 */
class QueueMonitorLog extends Model
{
    protected $table = 'queue_monitor_logs';

    protected $fillable = [
        'queue', 'job_class', 'status', 'duration_ms', 'attempt',
        'error_message', 'payload', 'queued_at', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
