<?php

namespace App\Console\Commands;

use App\Services\CommissionRiskGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnforceNegativeBalanceRecovery extends Command
{
    protected $signature = 'commission:enforce-recovery';
    protected $description = '执行负余额追缴 — 超30天未偿还的负余额账户冻结提现权限';

    public function handle(CommissionRiskGuard $riskGuard): int
    {
        $this->info('开始执行负余额追缴...');

        $result = $riskGuard->enforceNegativeBalanceRecovery();

        $this->info("警告: {$result['warned']} 个账户");
        $this->info("冻结: {$result['frozen']} 个账户");
        $this->info("已恢复: {$result['recovered']} 个账户");

        Log::info('负余额追缴执行完成', $result);

        return Command::SUCCESS;
    }
}
