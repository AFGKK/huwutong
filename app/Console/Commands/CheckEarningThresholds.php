<?php

namespace App\Console\Commands;

use App\Services\EarningsNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * M2-128 收益阈值告警命令
 *
 * 定时检查代理月收益是否达到里程碑阈值，触发告警通知。
 */
class CheckEarningThresholds extends Command
{
    protected $signature = 'earnings:check-thresholds
        {--dry-run : 仅统计不发送}';

    protected $description = '检查代理收益里程碑阈值并发送告警通知';

    public function handle(EarningsNotifier $notifier): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('检查收益里程碑阈值...');

        // 检查阈值
        $thresholds = [1000, 5000, 10000, 50000, 100000];

        if ($dryRun) {
            $count = \App\Models\Agent::where('status', 'active')->count();
            $this->info("[干运行] 将检查 {$count} 位活跃代理的 {$thresholds} 个阈值");
            return Command::SUCCESS;
        }

        $notifiedCount = $notifier->checkAndNotifyThresholds();

        $this->info("阈值检查完成，{$notifiedCount} 个里程碑触发通知");
        Log::info('收益阈值告警检查完成', [
            'notified_count' => $notifiedCount,
        ]);

        return Command::SUCCESS;
    }
}
