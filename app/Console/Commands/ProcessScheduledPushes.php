<?php

namespace App\Console\Commands;

use App\Services\MarketplacePushService;
use Illuminate\Console\Command;

class ProcessScheduledPushes extends Command
{
    protected $signature = 'marketplace:process-pushes';
    protected $description = '处理定时发送的市场推送活动';

    public function handle(MarketplacePushService $pushService): int
    {
        $sent = $pushService->processScheduledCampaigns();
        $this->info("已处理 {$sent} 个定时推送");
        return Command::SUCCESS;
    }
}
