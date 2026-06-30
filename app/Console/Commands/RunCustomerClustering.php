<?php

namespace App\Console\Commands;

use App\Services\CustomerClusteringService;
use Illuminate\Console\Command;

class RunCustomerClustering extends Command
{
    protected $signature = 'clustering:run {--tenant= : 指定租户ID}';
    protected $description = '执行客户行为聚类分析';

    public function handle(CustomerClusteringService $service): int
    {
        $tenantId = $this->option('tenant');
        if ($tenantId) {
            $result = $service->runClustering((int) $tenantId);
            $this->info("租户#{$tenantId}: {$result['assigned']}/{$result['total']} 客户已分配");
        } else {
            $this->warn('请指定租户ID: php artisan clustering:run --tenant=1');
        }

        return Command::SUCCESS;
    }
}
