<?php

namespace App\Console\Commands;

use App\Services\EarningsNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * M2-128 负余额预警命令
 *
 * 定时检查负余额账户，在关键时间点发送预警通知（第1、7、15、30天）。
 */
class CheckNegativeBalanceWarnings extends Command
{
    protected $signature = 'earnings:check-negative-balances
        {--dry-run : 仅统计不发送}';

    protected $description = '检查负余额账户并发送预警通知';

    public function handle(EarningsNotifier $notifier): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('检查负余额账户...');

        if ($dryRun) {
            $count = \App\Models\EarningsAccount::where('metadata->negative_balance', '>', 0)->count();
            $this->info("[干运行] 发现 {$count} 个负余额账户");
            return Command::SUCCESS;
        }

        $notifiedCount = $notifier->checkAndNotifyNegativeBalances();

        $this->info("负余额检查完成，{$notifiedCount} 个预警通知发送");
        Log::info('负余额预警检查完成', [
            'notified_count' => $notifiedCount,
        ]);

        return Command::SUCCESS;
    }
}
