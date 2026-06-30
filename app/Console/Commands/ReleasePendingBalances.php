<?php

namespace App\Console\Commands;

use App\Services\WithdrawalService;
use Illuminate\Console\Command;

class ReleasePendingBalances extends Command
{
    protected $signature = 'withdrawal:release-pending
        {--dry-run : 仅模拟，不实际解冻}';
    protected $description = 'T+30 自动解冻：将到期 pending_balance 释放到 available_balance (M3-72)';

    public function handle(WithdrawalService $withdrawalService): int
    {
        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] 模拟 T+30 到期解冻');
            $this->info('调用 CommissionRiskGuard::releaseExpiredFreezes() 检查到期冻结佣金');
            return self::SUCCESS;
        }

        $this->info('开始 T+30 到期冻结佣金解冻...');

        $count = $withdrawalService->releasePendingBalances();

        $this->info("完成！共处理了 {$count} 条到期冻结佣金。");

        return self::SUCCESS;
    }
}
