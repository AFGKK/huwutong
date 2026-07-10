<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 工作流实例
 *
 * 一次工作流的具体执行记录，包含上下文数据、当前状态和执行结果。
 *
 * @mixin IdeHelperWorkflowInstance
 */
class WorkflowInstance extends Model
{
    protected $fillable = [
        'workflow_name', 'workflowable_type', 'workflowable_id',
        'status', 'current_step', 'context', 'result',
        'error_message', 'retry_count', 'max_retries',
        'started_at', 'completed_at', 'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'result' => 'array',
            'retry_count' => 'integer',
            'max_retries' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_name', 'name');
    }

    public function workflowable()
    {
        return $this->morphTo();
    }

    public function stepExecutions(): HasMany
    {
        return $this->hasMany(WorkflowStepExecution::class);
    }

    public function timers(): HasMany
    {
        return $this->hasMany(WorkflowTimer::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markCompleted(?array $result = null): void
    {
        $this->update([
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
        ]);
    }

    public function markCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    /**
     * 是否还能重试
     */
    public function canRetry(): bool
    {
        return $this->retry_count < $this->max_retries;
    }

    /**
     * 增加重试计数
     */
    public function incrementRetry(): void
    {
        $this->increment('retry_count');
        $this->refresh();
    }
}
