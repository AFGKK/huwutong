<?php

namespace App\Console\Commands;

use App\Services\TenantIsolationService;
use Illuminate\Console\Command;

class RefreshTenantUsageSnapshots extends Command
{
    protected $signature = 'tenants:refresh-usage';
    protected $description = '刷新所有租户的用量快照';

    public function handle(TenantIsolationService $service): int
    {
        $this->info('开始刷新租户用量快照...');
        $count = $service->refreshAllSnapshots();
        $this->info("已完成，共刷新 {$count} 个租户");
        return Command::SUCCESS;
    }
}
