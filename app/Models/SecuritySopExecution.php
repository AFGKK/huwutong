<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecuritySopExecution extends Model
{
    protected $table = 'security_sop_executions';

    protected $fillable = [
        'tenant_id', 'sop_template_id', 'event_id',
        'triggered_by', 'execution_log', 'status',
        'total_steps', 'completed_steps', 'result_summary',
        'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'execution_log' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SecuritySopTemplate::class, 'sop_template_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SecurityEvent::class, 'event_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
