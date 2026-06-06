<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;

class BillingProcessRenewals extends Command
{
    protected $signature = 'billing:process-renewals';

    protected $description = '处理所有到期的订阅自动续费';

    public function handle(BillingService $billingService): void
    {
        $this->info('开始处理自动续费...');

        $stats = $billingService->processAutoRenewals();

        $this->table(
            ['指标', '数值'],
            [
                ['处理总数', $stats['processed']],
                ['续费成功', $stats['succeeded']],
                ['进入宽限期', $stats['grace_period']],
                ['失败', $stats['failed']],
            ],
        );

        $this->info('自动续费处理完成');
    }
}
