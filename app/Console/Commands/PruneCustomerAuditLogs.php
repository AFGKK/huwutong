<?php

namespace App\Console\Commands;

use App\Services\CustomerAuditLogService;
use Illuminate\Console\Command;

class PruneCustomerAuditLogs extends Command
{
    protected $signature = 'audit:prune-customer
                            {--days=90 : 保留天数，默认 90 天}
                            {--dry-run : 仅统计不删除}';

    protected $description = '清理客户侧审计日志（保留 90 天）';

    public function handle(CustomerAuditLogService $service): int
    {
        $retentionDays = (int) $this->option('days');
        $isDryRun = $this->option('dry-run');

        // 统计待清理数量
        $cutoff = now()->subDays($retentionDays)->endOfDay();
        $query = \App\Models\Log::where('type', 'audit')
            ->where('created_at', '<=', $cutoff);

        $count = $query->count();
        $this->info("客户侧审计日志清理（保留 {$retentionDays} 天）");
        $this->line("  截止日期: {$cutoff->toDateString()}");
        $this->line("  待清理条数: {$count}");

        if ($isDryRun || $count === 0) {
            if ($isDryRun) {
                $this->warn('DRY RUN 模式 — 未执行删除');
            }
            return Command::SUCCESS;
        }

        // 批量清理
        $deleted = $service->prune($retentionDays);
        $this->info("清理完成，共删除 {$deleted} 条记录");

        return Command::SUCCESS;
    }
}
