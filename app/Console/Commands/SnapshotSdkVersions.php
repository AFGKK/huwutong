<?php

namespace App\Console\Commands;

use App\Services\TelemetryService;
use Illuminate\Console\Command;

class SnapshotSdkVersions extends Command
{
    protected $signature = 'hwt:snapshot-sdk-versions';
    protected $description = '创建 SDK 版本分布日快照（统计最近 24h 活跃实例）';

    public function handle(TelemetryService $telemetryService): int
    {
        $this->info('Creating SDK version distribution snapshot...');

        $count = $telemetryService->snapshotVersionDistribution();

        $this->info("Created {$count} version snapshot records.");

        return Command::SUCCESS;
    }
}
