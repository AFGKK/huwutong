<?php

namespace App\Console\Commands;

use App\Services\RenewalPipelineService;
use Illuminate\Console\Command;

class BillingProcessRetries extends Command
{
    protected $signature = 'billing:process-retries';

    protected $description = '处理待重试的续费失败';

    public function handle(RenewalPipelineService $pipeline): void
    {
        $this->info('开始处理待重试的续费...');

        $stats = $pipeline->processRetries();

        $this->table(
            ['指标', '数值'],
            [
                ['尝试重试', $stats['attempted']],
                ['重试成功', $stats['succeeded']],
                ['重试失败', $stats['failed']],
                ['人工介入', $stats['escalated']],
            ],
        );

        $this->info('续费重试处理完成');
    }
}
