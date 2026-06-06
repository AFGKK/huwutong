<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;

class BillingProcessGracePeriod extends Command
{
    protected $signature = 'billing:process-grace-period';

    protected $description = '处理宽限期结束的订阅（停用/过期）';

    public function handle(BillingService $billingService): void
    {
        $this->info('开始处理宽限期...');

        $stats = $billingService->processGracePeriodEnded();

        $this->table(
            ['指标', '数值'],
            [
                ['已停用', $stats['suspended']],
                ['已过期', $stats['expired']],
            ],
        );

        $this->info('宽限期处理完成');
    }
}
