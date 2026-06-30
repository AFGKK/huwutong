<?php

namespace App\Console\Commands;

use App\Services\CommissionEngineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReleaseCommissions extends Command
{
    protected $signature = 'commission:release';
    protected $description = '释放已过冷静期的佣金结算';

    public function handle(CommissionEngineService $engine): int
    {
        $this->info('开始释放佣金...');

        $count = $engine->releasePendingSettlements();

        $this->info("已释放 {$count} 笔佣金结算");
        Log::info('佣金自动释放完成', ['count' => $count]);

        return Command::SUCCESS;
    }
}
