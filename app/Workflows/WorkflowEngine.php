<?php

namespace App\Workflows;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowTimer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 工作流引擎
 *
 * 协调工作流的创建、执行、重试和补偿。
 * 架构上与 Temporal 对齐：WorkflowStep → Temporal Activity, 本引擎 → Temporal Worker。
 * 后续迁移到真实 Temporal 时，只需替换 Engine 实现，步骤代码无需改动。
 */
class WorkflowEngine
{
    /**
     * 已注册的工作流步骤
     * @var array<string, array<string, WorkflowStep>>
     */
    private static array $steps = [];

    /**
     * 注册步骤
     */
    public static function registerStep(string $workflowName, WorkflowStep $step): void
    {
        self::$steps[$workflowName][$step->name()] = $step;
    }

    /**
     * 批量注册工作流
     *
     * @param string $workflowName
     * @param WorkflowStep[] $steps
     */
    public static function registerWorkflow(string $workflowName, array $steps): void
    {
        foreach ($steps as $step) {
            self::registerStep($workflowName, $step);
        }
    }

    /**
     * 获取已注册的步骤
     */
    public static function getStep(string $workflowName, string $stepName): ?WorkflowStep
    {
        return self::$steps[$workflowName][$stepName] ?? null;
    }

    /**
     * 创建并启动一个工作流
     *
     * @param string $workflowName 工作流名称（对应 workflow_definitions.name）
     * @param object|null $workflowable 关联模型
     * @param array $initialContext 初始上下文
     * @param array $options 选项: max_retries, start_delay
     * @return WorkflowInstance
     */
    public function start(
        string $workflowName,
        ?object $workflowable = null,
        array $initialContext = [],
        array $options = []
    ): WorkflowInstance {
        $instance = DB::transaction(function () use ($workflowName, $workflowable, $initialContext, $options) {
            $instance = WorkflowInstance::create([
                'workflow_name' => $workflowName,
                'workflowable_type' => $workflowable ? get_class($workflowable) : null,
                'workflowable_id' => $workflowable?->getKey(),
                'status' => 'running',
                'current_step' => null,
                'context' => $initialContext,
                'max_retries' => $options['max_retries'] ?? 3,
                'started_at' => now(),
            ]);

            Log::info('Workflow: started', [
                'instance_id' => $instance->id,
                'workflow' => $workflowName,
                'workflowable' => $workflowable ? get_class($workflowable).'#'.$workflowable->getKey() : null,
            ]);

            return $instance;
        });

        // 立即开始第一步
        $this->continue($instance);

        return $instance;
    }

    /**
     * 继续执行工作流（从当前步骤继续）
     */
    public function continue(WorkflowInstance $instance): void
    {
        if (!$instance->isRunning()) {
            return;
        }

        $definition = WorkflowDefinition::where('name', $instance->workflow_name)->first();
        $steps = $definition?->steps() ?? [];

        if (empty($steps)) {
            // 从注册的步骤中推断顺序
            $stepInstances = self::$steps[$instance->workflow_name] ?? [];
            $steps = array_map(fn(WorkflowStep $s) => ['name' => $s->name()], $stepInstances);
        }

        $currentIndex = $this->findCurrentStepIndex($instance, $steps);

        if ($currentIndex >= count($steps)) {
            $instance->markCompleted();
            Log::info('Workflow: completed', ['instance_id' => $instance->id]);
            return;
        }

        // 执行当前步骤
        $stepDef = $steps[$currentIndex];
        $stepName = $stepDef['name'];
        $step = self::getStep($instance->workflow_name, $stepName);

        if (!$step) {
            $instance->markFailed("步骤 {$stepName} 未注册");
            return;
        }

        $instance->update(['current_step' => $stepName]);

        $execution = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id,
            'step_name' => $stepName,
            'status' => 'running',
            'attempt' => $this->getCurrentAttempt($instance, $stepName),
            'max_attempts' => $step->maxRetries(),
            'started_at' => now(),
        ]);

        $context = $instance->context ?? [];

        try {
            $startTime = microtime(true);
            $result = $step->execute($instance, $context, $stepDef['input'] ?? []);
            $elapsed = (microtime(true) - $startTime) * 1000;

            // 更新步骤执行记录
            $execution->update([
                'status' => 'completed',
                'output' => $result,
                'completed_at' => now(),
            ]);

            // 更新上下文
            $instance->update(['context' => $context]);

            Log::info('Workflow: step completed', [
                'instance_id' => $instance->id,
                'step' => $stepName,
                'elapsed_ms' => round($elapsed, 2),
            ]);

            // 递归：执行下一步
            $this->continue($instance->fresh());

        } catch (\Throwable $e) {
            $execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            Log::warning('Workflow: step failed', [
                'instance_id' => $instance->id,
                'step' => $stepName,
                'error' => $e->getMessage(),
                'attempt' => $execution->attempt,
                'max_attempts' => $execution->max_attempts,
            ]);

            // 判断是否重试
            if ($execution->canRetry()) {
                $this->scheduleRetry($instance, $step, $execution);
            } elseif ($instance->canRetry()) {
                // 整体工作流重试
                $instance->incrementRetry();
                $delay = $this->getRetryDelay($instance);
                $this->scheduleRetry($instance, $step, $execution, $delay);
            } else {
                // 触发 Saga 补偿
                $this->compensate($instance, $steps, $stepName, $e->getMessage());
            }
        }
    }

    /**
     * Saga 补偿：逆序执行已成功步骤的 compensate
     */
    public function compensate(WorkflowInstance $instance, array $steps, string $failedStep, string $error): void
    {
        $instance->update([
            'status' => 'compensating',
            'error_message' => $error,
        ]);

        $completedSteps = WorkflowStepExecution::where('workflow_instance_id', $instance->id)
            ->where('status', 'completed')
            ->orderBy('id', 'desc') // 逆序
            ->get();

        $context = $instance->context ?? [];

        foreach ($completedSteps as $execution) {
            $step = self::getStep($instance->workflow_name, $execution->step_name);
            if (!$step) continue;

            try {
                $step->compensate(
                    $instance,
                    $context,
                    $execution->input ?? [],
                    $execution->output ?? []
                );

                $execution->update(['status' => 'compensated']);

                Log::info('Workflow: step compensated', [
                    'instance_id' => $instance->id,
                    'step' => $step->name(),
                ]);
            } catch (\Throwable $ce) {
                Log::error('Workflow: compensation failed', [
                    'instance_id' => $instance->id,
                    'step' => $step->name(),
                    'error' => $ce->getMessage(),
                ]);
                // 继续补偿其他步骤
            }
        }

        $instance->markFailed("步骤 {$failedStep} 失败，已执行补偿: {$error}");

        Log::warning('Workflow: failed with compensation', [
            'instance_id' => $instance->id,
            'completed_steps' => $completedSteps->count(),
            'error' => $error,
        ]);
    }

    /**
     * 调度重试
     */
    protected function scheduleRetry(
        WorkflowInstance $instance,
        WorkflowStep $step,
        WorkflowStepExecution $execution,
        ?int $customDelay = null
    ): void {
        $delays = is_array($step->retryDelay()) ? $step->retryDelay() : [$step->retryDelay()];
        $attemptIndex = $execution->attempt; // 0-based
        $delay = $customDelay ?? ($delays[min($attemptIndex, count($delays) - 1)] ?? 60);

        $fireAt = now()->addSeconds($delay);

        WorkflowTimer::create([
            'workflow_instance_id' => $instance->id,
            'timer_type' => 'retry',
            'fire_at' => $fireAt,
            'payload' => [
                'step_name' => $step->name(),
                'attempt' => $execution->attempt,
            ],
        ]);

        $instance->update(['next_retry_at' => $fireAt]);

        Log::info('Workflow: retry scheduled', [
            'instance_id' => $instance->id,
            'step' => $step->name(),
            'delay_seconds' => $delay,
            'fire_at' => $fireAt->toIso8601String(),
        ]);
    }

    /**
     * 处理到期的定时器（由调度命令调用）
     */
    public function processTimers(): array
    {
        $processed = ['retries' => 0, 'timeouts' => 0];

        $timers = WorkflowTimer::where('fire_at', '<=', now())
            ->where('fired', false)
            ->lockForUpdate()
            ->get();

        foreach ($timers as $timer) {
            try {
                DB::transaction(function () use ($timer) {
                    $timer->markFired();
                });

                $instance = $timer->workflowInstance;
                if ($instance && $instance->isRunning()) {
                    $this->continue($instance->fresh());
                }

                $processed[$timer->timer_type] = ($processed[$timer->timer_type] ?? 0) + 1;

            } catch (\Throwable $e) {
                Log::error('Workflow: timer processing failed', [
                    'timer_id' => $timer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * 取消工作流
     */
    public function cancel(WorkflowInstance $instance): void
    {
        $instance->markCancelled();

        Log::info('Workflow: cancelled', ['instance_id' => $instance->id]);
    }

    /**
     * 获取工作流状态
     */
    public function getStatus(WorkflowInstance $instance): array
    {
        $instance->load(['stepExecutions', 'timers']);

        $steps = $instance->stepExecutions->map(fn($e) => [
            'step' => $e->step_name,
            'status' => $e->status,
            'attempt' => $e->attempt,
            'error' => $e->error_message,
            'duration_ms' => $e->started_at && $e->completed_at
                ? $e->started_at->diffInMilliseconds($e->completed_at)
                : null,
        ]);

        return [
            'id' => $instance->id,
            'workflow' => $instance->workflow_name,
            'status' => $instance->status,
            'current_step' => $instance->current_step,
            'retry_count' => $instance->retry_count,
            'error' => $instance->error_message,
            'started_at' => $instance->started_at?->toIso8601String(),
            'completed_at' => $instance->completed_at?->toIso8601String(),
            'next_retry_at' => $instance->next_retry_at?->toIso8601String(),
            'steps' => $steps,
        ];
    }

    /**
     * 获取当前应该执行的步骤索引
     */
    protected function findCurrentStepIndex(WorkflowInstance $instance, array $steps): int
    {
        // 查找已完成的最后一步
        $lastCompleted = WorkflowStepExecution::where('workflow_instance_id', $instance->id)
            ->where('status', 'completed')
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastCompleted) {
            return 0;
        }

        foreach ($steps as $i => $step) {
            if ($step['name'] === $lastCompleted->step_name) {
                return $i + 1;
            }
        }

        return 0;
    }

    /**
     * 获取当前步骤的重试次数
     */
    protected function getCurrentAttempt(WorkflowInstance $instance, string $stepName): int
    {
        $last = WorkflowStepExecution::where('workflow_instance_id', $instance->id)
            ->where('step_name', $stepName)
            ->orderBy('id', 'desc')
            ->first();

        return ($last ? $last->attempt : 0) + 1;
    }

    /**
     * 计算工作流整体重试延迟
     */
    protected function getRetryDelay(WorkflowInstance $instance): int
    {
        $delays = [60, 300, 600]; // 1min, 5min, 10min
        $index = min($instance->retry_count, count($delays) - 1);
        return $delays[$index];
    }
}
