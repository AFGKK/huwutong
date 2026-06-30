<?php

namespace App\Console\Commands;

use App\Models\Log;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune
                            {--dry-run : 仅统计不删除}
                            {--type= : 仅清理指定类型的日志}';

    protected $description = '清理超过保留期的审计日志';

    public function handle(): int
    {
        $retentionConfig = config('audit.retention_days', [
            'audit' => 365,
            'security' => 365,
            'error' => 180,
            'system' => 90,
        ]);

        $typesToPrune = $this->option('type')
            ? [$this->option('type')]
            : array_keys($retentionConfig);

        $this->info('审计日志清理开始');
        $this->line('---');

        $totalDeleted = 0;

        foreach ($typesToPrune as $type) {
            $retentionDays = $retentionConfig[$type] ?? 365;
            $cutoff = now()->subDays($retentionDays)->toDateTimeString();

            $count = Log::ofType($type)->where('created_at', '<', $cutoff)->count();
            $this->line("类型 [{$type}] (保留 {$retentionDays} 天): 待清理 {$count} 条");

            if ($this->option('dry-run') || $count === 0) {
                continue;
            }

            $batchSize = config('audit.prune_batch_size', 1000);
            $deleted = 0;

            do {
                $batch = Log::ofType($type)
                    ->where('created_at', '<', $cutoff)
                    ->limit($batchSize)
                    ->delete();
                $deleted += $batch;
            } while ($batch > 0);

            $totalDeleted += $deleted;
            $this->info("  已清理 [{$type}]: {$deleted} 条");

            // 防止长时间运行
            if ($deleted > 0) {
                usleep(100000); // 100ms
            }
        }

        $this->line('---');
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN 模式 — 未执行删除');
        } else {
            $this->info("清理完成，共删除 {$totalDeleted} 条记录");
        }

        return Command::SUCCESS;
    }
}
