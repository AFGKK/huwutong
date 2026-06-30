<?php

namespace App\Console\Commands;

use App\Services\TwoPhaseCommitService;
use Illuminate\Console\Command;

class PruneExpiredReservations extends Command
{
    protected $signature = 'hwt:prune-expired-reservations';

    protected $description = '清理过期的授权预申请预留记录';

    public function handle(TwoPhaseCommitService $service): int
    {
        $count = $service->cleanupExpired();

        $this->info("已清理 {$count} 条过期预留记录");

        return Command::SUCCESS;
    }
}
