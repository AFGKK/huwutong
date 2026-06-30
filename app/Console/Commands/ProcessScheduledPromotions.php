<?php

namespace App\Console\Commands;

use App\Services\ScheduledPromotionService;
use Illuminate\Console\Command;

class ProcessScheduledPromotions extends Command
{
    protected $signature = 'promotions:process-scheduled';
    protected $description = '处理定时促销活动（自动激活/过期）';

    public function handle(ScheduledPromotionService $service): int
    {
        $this->info('开始处理定时促销...');

        $result = $service->processScheduledPromotions();

        $this->info("已激活: {$result['activated']} 个活动");
        $this->info("已过期: {$result['expired']} 个活动");

        return Command::SUCCESS;
    }
}
