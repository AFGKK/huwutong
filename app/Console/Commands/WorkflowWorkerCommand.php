<?php

namespace App\Console\Commands;

use App\Models\WorkflowInstance;
use App\Workflows\WorkflowEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * M2-137 Temporal 工作流 Worker
 *
 * 模拟 Temporal Worker 的行为：
 * - 从队列中拉取待执行的工作流
 * - 按并发限制执行步骤
 * - 心跳监控
 * - 优雅关闭
 *
 * 生产环境应迁移到真实 Temporal Worker (RoadRunner + temporal/sdk)。
 *
 * 用法:
 *   php artisan workflow:worker start           # 启动 Worker
 *   php artisan workflow:worker status          # 查看 Worker 状态
 *   php artisan workflow:worker stop            # 优雅停止
 */
class WorkflowWorkerCommand extends Command
{
    protected $signature = 'workflow:worker
                            {action : start|status|stop}
                            {--queue= : 任务队列名称}
                            {--concurrent=10 : 最大并发数}
                            {--timeout=300 : 执行超时(秒)}';

    protected $description = 'Temporal 工作流 Worker 管理';

    public function handle(WorkflowEngine $engine): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'start' => $this->startWorker($engine),
            'status' => $this->showStatus(),
            'stop' => $this->stopWorker(),
            default => $this->error("未知操作: {$action}，支持 start|status|stop"),
        };
    }

    /**
     * 启动 Worker（单次轮询模式，由定时任务调度）
     */
    protected function startWorker(WorkflowEngine $engine): int
    {
        $queue = $this->option('queue') ?? config('temporal.engine.temporal.task_queue', 'license-workflows');
        $concurrent = (int) $this->option('concurrent');
        $timeout = (int) $this->option('timeout');

        $this->info("Temporal Worker 启动 [队列: {$queue}, 并发: {$concurrent}, 超时: {$timeout}s]");

        // 获取待执行的工作流实例
        $pending = WorkflowInstance::where('status', 'running')
            ->where(function ($q) {
                $q->whereNull('started_at')
                  ->orWhere('started_at', '<=', now());
            })
            ->orderBy('created_at')
            ->limit($concurrent)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('没有待执行的工作流');
            return 0;
        }

        $this->info("发现 {$pending->count()} 个待执行工作流");

        $processed = 0;
        $failed = 0;

        foreach ($pending as $instance) {
            try {
                $engine->continue($instance->fresh());
                $processed++;
                $this->line("  ✓ {$instance->workflow_name}#{$instance->id}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ✗ {$instance->workflow_name}#{$instance->id}: {$e->getMessage()}");
                Log::error('Worker: execution failed', [
                    'instance_id' => $instance->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("处理完成: {$processed} 成功, {$failed} 失败");

        return $failed > 0 ? 1 : 0;
    }

    /**
     * 查看 Worker 状态
     */
    protected function showStatus(): int
    {
        $running = WorkflowInstance::where('status', 'running')->count();
        $pending = WorkflowInstance::where('status', 'pending')->count();
        $failed = WorkflowInstance::where('status', 'failed')->count();
        $compensating = WorkflowInstance::where('status', 'compensating')->count();

        $this->info('Temporal Worker 状态');
        $this->newLine();

        $this->table(
            ['指标', '数值'],
            [
                ['队列', config('temporal.engine.temporal.task_queue', 'license-workflows')],
                ['运行中', $running],
                ['待执行', $pending],
                ['失败', $failed],
                ['补偿中', $compensating],
                ['并发上限', config('temporal.execution.max_concurrent', 10)],
                ['心跳间隔', config('temporal.execution.heartbeat_seconds', 30) . 's'],
                ['执行超时', config('temporal.execution.timeout_minutes', 60) . 'min'],
            ]
        );

        // 按工作流类型统计
        $byWorkflow = WorkflowInstance::selectRaw(
            'workflow_name, COUNT(*) as total,
             SUM(CASE WHEN status = "running" THEN 1 ELSE 0 END) as running,
             SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'
        )->groupBy('workflow_name')->get();

        if ($byWorkflow->isNotEmpty()) {
            $this->newLine();
            $this->info('按工作流类型统计');
            $this->table(
                ['工作流', '总数', '运行中', '失败'],
                $byWorkflow->toArray()
            );
        }

        return 0;
    }

    /**
     * 停止 Worker（清除运行中的标记）
     */
    protected function stopWorker(): int
    {
        $count = WorkflowInstance::where('status', 'running')
            ->whereNull('completed_at')
            ->update(['status' => 'pending']);

        $this->info("已暂停 {$count} 个工作流实例");
        $this->warn('Worker 已停止。运行 workflow:worker start 重新启动。');

        return 0;
    }
}
