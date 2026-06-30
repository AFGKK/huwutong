<?php

namespace App\Console\Commands;

use App\Models\WorkflowInstance;
use App\Services\TemporalWorkflowService;
use Illuminate\Console\Command;

/**
 * M2-137 工作流列表与维护命令
 *
 * 用法:
 *   php artisan workflow:list                    # 列出所有工作流
 *   php artisan workflow:list --status=failed    # 列出失败的工作流
 *   php artisan workflow:retry {id}              # 重试指定工作流
 *   php artisan workflow:retry --all-failed      # 重试所有失败工作流
 *   php artisan workflow:cleanup                 # 清理过期工作流
 */
class WorkflowListCommand extends Command
{
    protected $signature = 'workflow:list
                            {--status= : 按状态筛选 running|completed|failed|cancelled}
                            {--workflow= : 按工作流名称筛选}
                            {--limit=20 : 显示条数}';

    protected $description = '列出和管理工作流实例';

    public function handle(): int
    {
        $query = WorkflowInstance::orderByDesc('created_at');

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }
        if ($workflow = $this->option('workflow')) {
            $query->where('workflow_name', $workflow);
        }

        $instances = $query->limit((int) $this->option('limit'))->get();

        if ($instances->isEmpty()) {
            $this->info('没有匹配的工作流');
            return 0;
        }

        $this->info("工作流实例列表 ({$instances->count()} 条)");
        $this->newLine();

        $this->table(
            ['ID', '工作流', '状态', '当前步骤', '重试', '错误', '创建时间'],
            $instances->map(fn($i) => [
                $i->id,
                $i->workflow_name,
                $i->status,
                $i->current_step ?? '-',
                "{$i->retry_count}/{$i->max_retries}",
                $i->error_message ? substr($i->error_message, 0, 50) : '-',
                $i->created_at->toDateTimeString(),
            ])->toArray()
        );

        return 0;
    }
}
