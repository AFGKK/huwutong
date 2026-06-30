<?php

namespace App\Console\Commands;

use App\Services\SlaProbeService;
use Illuminate\Console\Command;

class RunSlaProbes extends Command
{
    protected $signature = 'hwt:run-sla-probes';

    protected $description = '执行所有到期的SLA自动化拨测';

    public function handle(SlaProbeService $probeService): int
    {
        $count = $probeService->runAllDue();

        $this->info("已完成 {$count} 次拨测");

        return Command::SUCCESS;
    }
}
