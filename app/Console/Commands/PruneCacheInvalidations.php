<?php

namespace App\Console\Commands;

use App\Services\CacheInvalidationPushService;
use Illuminate\Console\Command;

class PruneCacheInvalidations extends Command
{
    protected $signature = 'cache-invalidation:prune
                            {--days=7 : 保留的天数}
                            {--dry-run : 仅显示要删除的记录数}';

    protected $description = '清理已发布的缓存失效记录（超过保留期）';

    public function handle(CacheInvalidationPushService $pushService): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $count = CacheInvalidation::where('status', 'published')
                ->where('created_at', '<', now()->subDays($days))
                ->count();

            $this->info("[DRY RUN] 将删除 {$count} 条已发布的缓存失效记录（超过 {$days} 天）");
            return Command::SUCCESS;
        }

        $deleted = $pushService->prune($days);
        $this->info("已清理 {$deleted} 条过期缓存失效记录");

        return Command::SUCCESS;
    }
}
