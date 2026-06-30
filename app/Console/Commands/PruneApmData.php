<?php

namespace App\Console\Commands;

use App\Models\ApmRequest;
use Illuminate\Console\Command;

class PruneApmData extends Command
{
    protected $signature = 'apm:prune
                            {--dry-run : 仅统计不删除}
                            {--days=90 : 保留天数}';

    protected $description = '清理超过保留期的 APM 数据';

    public function handle(): int
    {
        $retentionDays = (int) $this->option('days');
        $cutoff = now()->subDays($retentionDays);
        $dryRun = $this->option('dry-run');

        $query = ApmRequest::where('created_at', '<', $cutoff);

        if ($dryRun) {
            $count = $query->count();
            $this->info("【DRY RUN】将清理 {$count} 条 APM 记录（超过 {$retentionDays} 天）");
            return self::SUCCESS;
        }

        $count = $query->delete();

        $this->info("已清理 {$count} 条 APM 记录（超过 {$retentionDays} 天）");

        return self::SUCCESS;
    }
}
