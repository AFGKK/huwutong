<?php

namespace App\Console\Commands;

use App\Models\WorkflowInstance;
use App\Services\TemporalWorkflowService;
use App\Workflows\WorkflowEngine;
use Illuminate\Console\Command;

/**
 * M2-137 工作流重试命令
 *
 * 用法:
 *   php artisan workflow:retry {id}              # 重试指定工作流
 *   php artisan workflow:retry --all-failed      # 重试所有失败的工作流
 *   php artisan workflow:retry --workflow=license_expiry  # 重试指定类型的失败工作流
 */
class WorkflowRetryCommand extends Command
{
    protected $signature = 'workflow:retry
                            {id? : 工作流实例ID}
                            {--all-failed : 重试所有失败的}
                            {--workflow= : 按工作流类型筛选}
                            {--dry-run : 仅预览不执行}';

    protected $description = '重试失败的工作流实例';

    public function handle(WorkflowEngine $engine): int
    {
        $id = $this->argument('id');
        $allFailed = $this->option('all-failed');
        $workflow = $this->option('workflow');
        $dryRun = $this->option('dry-run');

        if (! $id && ! $allFailed) {
            $this->error('请指定工作流ID或使用 --all-failed');
            return 1;
        }

        if ($id) {
            return $this->retrySingle((int) $id, $engine);
        }

        return $this->retryAllFailed($engine, $workflow, $dryRun);
    }

    protected function retrySingle(int $id, WorkflowEngine $engine): int
    {
        $instance = WorkflowInstance::find($id);

        if (! $instance) {
            $this->error("工作流 #{$id} 不存在");
            return 1;
        }

        if (! $instance->isFailed()) {
            $this->error("工作流 #{$id} 状态为 {$instance->status}，只能重试失败的工作流");
            return 1;
        }

        $this->info("正在重试工作流 #{$id} ({$instance->workflow_name})...");

        $instance->update([
            'status' => 'running',
            'retry_count' => 0,
            'error_message' => null,
            'completed_at' => null,
        ]);
        $instance->timers()->where('fired', false)->delete();
        $instance->stepExecutions()->where('status', 'failed')->delete();

        $engine->continue($instance->fresh());

        $this->info("✓ 工作流 #{$id} 已重新开始");
        return 0;
    }

    protected function retryAllFailed(WorkflowEngine $engine, ?string $workflow, bool $dryRun): int
    {
        $query = WorkflowInstance::where('status', 'failed');

        if ($workflow) {
            $query->where('workflow_name', $workflow);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('没有失败的工作流');
            return 0;
        }

        if ($dryRun) {
            $this->info("[DRY RUN] 将重试 {$total} 个失败工作流" . ($workflow ? " (类型: {$workflow})" : ''));
            return 0;
        }

        $this->info("正在重试 {$total} 个失败工作流...");

        $success = 0;
        $failed = 0;

        $query->chunk(50, function ($instances) use ($engine, &$success, &$failed) {
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

                    $engine->continue($instance->fresh());
                    $success++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("重试 #{$instance->id} 失败: {$e->getMessage()}");
                }
            }
        });

        $this->newLine();
        $this->info("重试完成: {$success} 成功, {$failed} 失败");
        return $failed > 0 ? 1 : 0;
    }
}
