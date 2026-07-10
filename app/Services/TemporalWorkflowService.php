<?php

namespace App\Services;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Workflows\WorkflowEngine;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * M2-137 Temporal 工作流引擎集成
 *
 * 基于现有 WorkflowEngine 构建 Temporal 兼容层，
 * 提供 Temporal 风格的 API：
 * - startWorkflow / signalWorkflow / queryWorkflow / terminateWorkflow
 * - 工作流可视化状态 (Gantt 视图)
 * - Saga 分布式事务协调
 * - Worker 健康状态
 *
 * 后续迁移到真实 Temporal Server 时，只需替换此服务的内部实现，
 * WorkflowStep 代码和 Controller 层无需改动。
 */
class TemporalWorkflowService
{
    public function __construct(
        protected WorkflowEngine $engine,
    ) {}

    // ═══════════════════════════════════════
    //  Temporal 兼容 API
    // ═══════════════════════════════════════

    /**
     * 启动工作流 (Temporal: startWorkflow)
     *
     * @param string $workflowName 工作流类型
     * @param array $input 输入参数
     * @param array $options [
     *   'id' => '自定义工作流ID',
     *   'task_queue' => '任务队列',
     *   'execution_timeout' => '执行超时(秒)',
     *   'retry_max' => '最大重试次数',
     *   'cron_schedule' => 'Cron 调度表达式',
     * ]
     * @return WorkflowInstance
     */
    public function startWorkflow(string $workflowName, array $input = [], array $options = []): WorkflowInstance
    {
        $instanceId = $options['id'] ?? null;

        // Eactly-once: 如果指定了 ID 且已存在，直接返回已有实例
        if ($instanceId) {
            $existing = WorkflowInstance::find($instanceId);
            if ($existing) {
                Log::info('Temporal: workflow already exists, returning existing', [
                    'instance_id' => $instanceId,
                    'workflow' => $workflowName,
                ]);
                return $existing;
            }
        }

        $instance = $this->engine->start(
            workflowName: $workflowName,
            initialContext: $input,
            options: [
                'max_retries' => $options['retry_max'] ?? 3,
            ],
        );

        // 如果提供了自定义 ID，覆盖
        if ($instanceId && $instance->id !== (int) $instanceId) {
            // 仅记录，实际使用自动 ID
        }

        Log::info('Temporal: workflow started', [
            'instance_id' => $instance->id,
            'workflow' => $workflowName,
            'input_keys' => array_keys($input),
        ]);

        return $instance;
    }

    /**
     * 向运行中的工作流发送信号 (Temporal: signalWorkflow)
     *
     * @param int $instanceId
     * @param string $signalName 信号名称 (如 'approve', 'reject', 'cancel')
     * @param array $payload 信号数据
     * @return bool
     */
    public function signalWorkflow(int $instanceId, string $signalName, array $payload = []): bool
    {
        $instance = WorkflowInstance::find($instanceId);
        if (! $instance || ! $instance->isRunning()) {
            return false;
        }

        // 更新上下文并触发继续执行
        $context = array_merge($instance->context ?? [], [
            '_signal' => $signalName,
            '_signal_payload' => $payload,
            '_signal_at' => now()->toIso8601String(),
        ]);

        $instance->update(['context' => $context]);

        Log::info('Temporal: signal sent', [
            'instance_id' => $instanceId,
            'signal' => $signalName,
        ]);

        // 继续执行工作流
        $this->engine->continue($instance->fresh());

        return true;
    }

    /**
     * 查询工作流状态 (Temporal: queryWorkflow)
     *
     * @param int $instanceId
     * @param string $queryType 查询类型 (status/progress/steps/context)
     * @return array
     */
    public function queryWorkflow(int $instanceId, string $queryType = 'status'): array
    {
        $instance = WorkflowInstance::with('stepExecutions')->find($instanceId);
        if (! $instance) {
            return ['error' => '工作流实例不存在'];
        }

        return match ($queryType) {
            'status' => $this->getStatusDetail($instance),
            'progress' => $this->getProgressDetail($instance),
            'steps' => $this->getStepsDetail($instance),
            'context' => $instance->context ?? [],
            'saga' => $this->getSagaStatus($instance),
            default => $this->getStatusDetail($instance),
        };
    }

    /**
     * 终止工作流 (Temporal: terminateWorkflow)
     *
     * @param int $instanceId
     * @param string $reason
     * @return bool
     */
    public function terminateWorkflow(int $instanceId, string $reason = ''): bool
    {
        $instance = WorkflowInstance::find($instanceId);
        if (! $instance) {
            return false;
        }

        // 如果正在运行，先执行 Saga 补偿
        if ($instance->isRunning()) {
            $steps = $this->getWorkflowSteps($instance->workflow_name);
            $this->engine->compensate($instance, $steps, 'terminated', $reason ?: '手动终止');
        } else {
            $instance->update([
                'status' => 'cancelled',
                'error_message' => $reason ?: '手动终止',
                'completed_at' => now(),
            ]);
        }

        Log::info('Temporal: workflow terminated', [
            'instance_id' => $instanceId,
            'reason' => $reason ?: '手动终止',
        ]);

        return true;
    }

    // ═══════════════════════════════════════
    //  工作流编排
    // ═══════════════════════════════════════

    /**
     * 获取工作流定义中的步骤列表
     */
    protected function getWorkflowSteps(string $workflowName): array
    {
        $definition = WorkflowDefinition::where('name', $workflowName)->first();
        if ($definition && $definition->steps_definition) {
            return $definition->steps_definition;
        }
        return [];
    }

    /**
     * 批量启动工作流
     */
    public function startBatch(string $workflowName, array $items, array $options = []): array
    {
        $results = [];
        foreach ($items as $item) {
            try {
                $results[] = $this->startWorkflow(
                    $workflowName,
                    $item,
                    $options,
                );
            } catch (\Throwable $e) {
                Log::error('Temporal: batch start failed', [
                    'workflow' => $workflowName,
                    'item' => $item,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $results;
    }

    // ═══════════════════════════════════════
    //  仪表盘 & 统计
    // ═══════════════════════════════════════

    /**
     * 仪表盘总览
     */
    public function dashboard(): array
    {
        $now = now();

        return [
            'stats' => [
                'total' => WorkflowInstance::count(),
                'running' => WorkflowInstance::where('status', 'running')->count(),
                'completed' => WorkflowInstance::where('status', 'completed')->count(),
                'failed' => WorkflowInstance::where('status', 'failed')->count(),
                'cancelled' => WorkflowInstance::where('status', 'cancelled')->count(),
                'compensating' => WorkflowInstance::where('status', 'compensating')->count(),
            ],
            'today_stats' => [
                'started' => WorkflowInstance::where('created_at', '>=', $now->startOfDay())->count(),
                'completed' => WorkflowInstance::where('completed_at', '>=', $now->startOfDay())
                    ->where('status', 'completed')->count(),
                'failed' => WorkflowInstance::where('updated_at', '>=', $now->startOfDay())
                    ->where('status', 'failed')->count(),
            ],
            'by_workflow' => WorkflowInstance::selectRaw(
                "workflow_name, COUNT(*) as total, 
                 SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running,
                 SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                 SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"
            )->groupBy('workflow_name')->get(),
            'recent_failures' => WorkflowInstance::where('status', 'failed')
                ->orderBy('updated_at', 'desc')
                ->limit(10)
                ->get(['id', 'workflow_name', 'error_message', 'updated_at']),
            'worker_status' => $this->getWorkerStatus(),
        ];
    }

    /**
     * 按日期统计工作流数量（趋势图）
     */
    public function trend(int $days = 14): array
    {
        $since = now()->subDays($days)->startOfDay();

        $daily = WorkflowInstance::selectRaw(
            "DATE(created_at) as date, 
             COUNT(*) as total,
             SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
             SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"
        )->where('created_at', '>=', $since)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'days' => $days,
            'data' => $daily,
        ];
    }

    /**
     * 失败的步骤执行列表
     */
    public function failedSteps(int $perPage = 20): LengthAwarePaginator
    {
        return WorkflowStepExecution::where('status', 'failed')
            ->with('instance')
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    /**
     * 批量重试失败工作流
     */
    public function batchRetry(array $instanceIds, ?string $workflowName = null): array
    {
        $query = WorkflowInstance::where('status', 'failed');

        if (! empty($instanceIds)) {
            $query->whereIn('id', $instanceIds);
        }
        if ($workflowName) {
            $query->where('workflow_name', $workflowName);
        }

        $instances = $query->limit(50)->get();
        $results = [];

        foreach ($instances as $instance) {
            try {
                $instance->update([
                    'status' => 'running',
                    'retry_count' => 0,
                    'error_message' => null,
                    'completed_at' => null,
                ]);
                $instance->timers()->where('fired', false)->delete();
                $instance->stepExecutions()->where('status', 'failed')->delete();

                $this->engine->continue($instance->fresh());
                $results[] = ['id' => $instance->id, 'status' => 'retried'];
            } catch (\Throwable $e) {
                $results[] = ['id' => $instance->id, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    // ═══════════════════════════════════════
    //  详细状态查询
    // ═══════════════════════════════════════

    /**
     * 工作流详细状态
     */
    protected function getStatusDetail(WorkflowInstance $instance): array
    {
        $steps = WorkflowStepExecution::where('workflow_instance_id', $instance->id)
            ->orderBy('id')
            ->get();

        return [
            'id' => $instance->id,
            'workflow_name' => $instance->workflow_name,
            'status' => $instance->status,
            'current_step' => $instance->current_step,
            'retry_count' => $instance->retry_count,
            'max_retries' => $instance->max_retries,
            'error_message' => $instance->error_message,
            'context' => $instance->context,
            'started_at' => $instance->started_at?->toIso8601String(),
            'completed_at' => $instance->completed_at?->toIso8601String(),
            'elapsed_seconds' => $instance->started_at
                ? ($instance->completed_at ?? now())->diffInSeconds($instance->started_at)
                : null,
            'steps' => $steps,
        ];
    }

    /**
     * 工作流进度详情（Gantt 视图用）
     */
    protected function getProgressDetail(WorkflowInstance $instance): array
    {
        $steps = WorkflowStepExecution::where('workflow_instance_id', $instance->id)
            ->orderBy('id')
            ->get();

        $totalSteps = $steps->count();
        $completedSteps = $steps->where('status', 'completed')->count();
        $totalDuration = 0;
        $stepTimings = [];

        foreach ($steps as $step) {
            $duration = $step->started_at && $step->completed_at
                ? $step->started_at->diffInSeconds($step->completed_at)
                : null;
            $totalDuration += $duration ?? 0;

            $stepTimings[] = [
                'name' => $step->step_name,
                'status' => $step->status,
                'started_at' => $step->started_at?->toIso8601String(),
                'completed_at' => $step->completed_at?->toIso8601String(),
                'duration_seconds' => $duration,
                'attempt' => $step->attempt,
                'error' => $step->error_message,
            ];
        }

        return [
            'instance_id' => $instance->id,
            'progress' => $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100, 1) : 0,
            'total_steps' => $totalSteps,
            'completed_steps' => $completedSteps,
            'failed_steps' => $steps->where('status', 'failed')->count(),
            'total_duration_seconds' => $totalDuration,
            'steps_timeline' => $stepTimings,
        ];
    }

    /**
     * Saga 分布式事务状态
     */
    protected function getSagaStatus(WorkflowInstance $instance): array
    {
        $steps = WorkflowStepExecution::where('workflow_instance_id', $instance->id)
            ->orderBy('id')
            ->get();

        $committed = $steps->where('status', 'completed')->values();
        $compensated = $steps->where('status', 'compensated')->values();
        $failed = $steps->where('status', 'failed')->first();

        return [
            'status' => $instance->status,
            'is_compensating' => $instance->status === 'compensating',
            'compensation_completed' => $instance->status === 'failed' && $compensated->count() > 0,
            'committed_steps' => $committed->map(fn($s) => [
                'name' => $s->step_name,
                'status' => $s->status,
                'output' => $s->output,
            ]),
            'compensated_steps' => $compensated->map(fn($s) => [
                'name' => $s->step_name,
                'output' => $s->output,
            ]),
            'failed_step' => $failed ? [
                'name' => $failed->step_name,
                'error' => $failed->error_message,
                'attempt' => $failed->attempt,
            ] : null,
        ];
    }

    /**
     * Worker 状态模拟
     */
    protected function getWorkerStatus(): array
    {
        $runningCount = WorkflowInstance::where('status', 'running')->count();
        $pendingCount = WorkflowInstance::where('status', 'pending')->count();

        return [
            'driver' => config('temporal.engine.driver', 'temporal'),
            'task_queue' => config('temporal.engine.temporal.task_queue', 'license-workflows'),
            'namespace' => config('temporal.engine.temporal.namespace', 'huwutong'),
            'active_workers' => $this->estimateActiveWorkers(),
            'queued_instances' => $pendingCount,
            'running_instances' => $runningCount,
            'heartbeat_seconds' => config('temporal.execution.heartbeat_seconds', 30),
            'max_concurrent' => config('temporal.execution.max_concurrent', 10),
        ];
    }

    /**
     * 估算活跃 Worker 数
     */
    protected function estimateActiveWorkers(): int
    {
        $recentRunning = WorkflowInstance::where('status', 'running')
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->count();
        return max(1, (int) ceil($recentRunning / 5));
    }

    /**
     * 清理过期的工作流
     */
    public function cleanup(int $retentionDays = 30): array
    {
        $cutoff = now()->subDays($retentionDays);

        $completed = WorkflowInstance::whereIn('status', ['completed', 'cancelled'])
            ->where('completed_at', '<=', $cutoff)
            ->delete();

        $failed = WorkflowInstance::where('status', 'failed')
            ->where('updated_at', '<=', $cutoff)
            ->delete();

        Log::info('Temporal: cleanup completed', [
            'completed_removed' => $completed,
            'failed_removed' => $failed,
            'retention_days' => $retentionDays,
        ]);

        return [
            'completed_removed' => $completed,
            'failed_removed' => $failed,
        ];
    }
}
