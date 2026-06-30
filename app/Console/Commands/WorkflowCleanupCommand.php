<?php

namespace App\Console\Commands;

use App\Services\TemporalWorkflowService;
use Illuminate\Console\Command;

/**
 * M2-137 工作流清理命令
 *
 * 清理已完成的过期工作流实例，释放数据库空间。
 *
 * 用法:
 *   php artisan workflow:cleanup                # 清理30天前已完成的工作流
 *   php artisan workflow:cleanup --days=7       # 清理7天前的
 *   php artisan workflow:cleanup --dry-run      # 仅预览
 */
class WorkflowCleanupCommand extends Command
{
    protected $signature = 'workflow:cleanup
                            {--days=30 : 保留天数}
                            {--dry-run : 仅预览不删除}';

    protected $description = '清理过期的工作流实例';

    public function handle(TemporalWorkflowService $service): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $cutoff = now()->subDays($days);

        $completedCount = \App\Models\WorkflowInstance::whereIn('status', ['completed', 'cancelled'])
            ->where('completed_at', '<=', $cutoff)
            ->count();

        $failedCount = \App\Models\WorkflowInstance::where('status', 'failed')
            ->where('updated_at', '<=', $cutoff)
            ->count();

        $total = $completedCount + $failedCount;

        if ($total === 0) {
            $this->info("没有超过 {$days} 天的过期工作流");
            return 0;
        }

        if ($dryRun) {
            $this->info("[DRY RUN] 将清理:");
            $this->line("  - 已完成/已取消: {$completedCount}");
            $this->line("  - 失败: {$failedCount}");
            $this->line("  - 合计: {$total}");
            return 0;
        }

        $this->info("正在清理 {$days} 天前的过期工作流...");

        $result = $service->cleanup($days);

        $this->table(
            ['类型', '清理数量'],
            [
                ['已完成/已取消', $result['completed_removed']],
                ['失败', $result['failed_removed']],
            ]
        );

        $removed = $result['completed_removed'] + $result['failed_removed'];
        $this->info("清理完成，共移除 {$removed} 个过期工作流");

        return 0;
    }
}
