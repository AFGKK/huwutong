<?php

namespace App\Console\Commands;

use App\Services\EarningsNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * M2-128 月度收益报告命令
 *
 * 每月1日凌晨自动发送上月收益报告给所有活跃代理。
 */
class SendMonthlyEarningReports extends Command
{
    protected $signature = 'earnings:send-monthly-reports
        {--period= : 指定报告月份（格式：Y-m，默认上月）}
        {--dry-run : 仅统计不发送}';

    protected $description = '发送月度收益报告给所有活跃代理';

    public function handle(EarningsNotifier $notifier): int
    {
        $period = $this->option('period') ?: now()->subMonth()->format('Y-m');
        $dryRun = $this->option('dry-run');

        $this->info("发送 {$period} 月度收益报告...");

        if ($dryRun) {
            $agentCount = \App\Models\Agent::where('status', 'active')
                ->whereHas('user', fn($q) => $q->whereNotNull('email'))
                ->count();
            $this->info("[干运行] 将向 {$agentCount} 位活跃代理发送月度报告");
            return Command::SUCCESS;
        }

        $count = $notifier->sendBulkMonthlyReports($period);

        $this->info("已发送 {$count} 份月度收益报告");
        Log::info('月度收益报告发送完成', [
            'period' => $period,
            'sent_count' => $count,
        ]);

        return Command::SUCCESS;
    }
}
