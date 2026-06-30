<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 工作流步骤执行记录
 */
class WorkflowStepExecution extends Model
{
    protected $fillable = [
        'workflow_instance_id', 'step_name',
        'status', 'input', 'output',
        'error_message', 'attempt', 'max_attempts',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'attempt' => 'integer',
            'max_attempts' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function canRetry(): bool
    {
        return $this->attempt < $this->max_attempts;
    }
}
