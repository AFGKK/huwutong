<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 工作流定时器
 *
 * 用于延迟执行、超时检测、重试调度等时间相关操作。
 *
 * @mixin IdeHelperWorkflowTimer
 */
class WorkflowTimer extends Model
{
    protected $fillable = [
        'workflow_instance_id', 'timer_type',
        'fire_at', 'payload', 'fired',
    ];

    protected function casts(): array
    {
        return [
            'fire_at' => 'datetime',
            'payload' => 'array',
            'fired' => 'boolean',
        ];
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    public function markFired(): void
    {
        $this->update(['fired' => true]);
    }
}
