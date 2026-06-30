<?php

namespace App\Console\Commands;

use App\Services\SloBudgetService;
use Illuminate\Console\Command;

class CalculateSloBudgets extends Command
{
    protected $signature = 'hwt:calculate-slo-budgets';

    protected $description = '计算所有活跃SLO的错误预算';

    public function handle(SloBudgetService $sloBudgetService): int
    {
        $count = $sloBudgetService->calculateAllBudgets();

        $this->info("已计算 {$count} 个SLO的错误预算");

        return Command::SUCCESS;
    }
}
