<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLogAggregationEntry
 */
class LogAggregationEntry extends Model
{
    protected $fillable = [
        'index_id', 'trace_id', 'channel', 'level', 'message',
        'context', 'extra', 'file', 'line', 'ip', 'user_agent',
        'user_id', 'tenant_id', 'request_method', 'request_path',
        'response_status', 'duration_ms', 'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'extra' => 'array',
            'logged_at' => 'datetime',
            'duration_ms' => 'float',
        ];
    }

    public function index()
    {
        return $this->belongsTo(LogAggregationIndex::class, 'index_id');
    }
}
