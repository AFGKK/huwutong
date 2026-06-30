<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\GlobalSearchService;
use Illuminate\Console\Command;

class RebuildSearchIndex extends Command
{
    protected $signature = 'search:rebuild {--type=all : 索引类型: license/customer/product/ticket/invoice/subscription/all} {--tenant-id= : 指定租户ID}';
    protected $description = '重建搜索索引';

    public function handle(GlobalSearchService $searchService): int
    {
        $type = $this->option('type');
        $tenantId = $this->option('tenant-id');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("租户 {$tenantId} 不存在");
                return Command::FAILURE;
            }
            return $this->rebuildForTenant($searchService, $tenant, $type);
        }

        // 为所有活跃租户重建
        $tenants = Tenant::where('status', 'active')->get();
        $this->info("将为 {$tenants->count()} 个活跃租户重建索引...");

        foreach ($tenants as $tenant) {
            $this->line("  处理租户: {$tenant->name}");
            $result = $this->rebuildForTenant($searchService, $tenant, $type, true);
            $this->line("    {$result}");
        }

        $this->info('全部完成');
        return Command::SUCCESS;
    }

    protected function rebuildForTenant(GlobalSearchService $service, Tenant $tenant, string $type, bool $silent = false): string
    {
        if ($type === 'all') {
            $results = $service->rebuildAll($tenant->id);
            $total = array_sum(array_filter($results, 'is_int'));
            return $silent ? "索引完成: {$total} 条" : "租户 {$tenant->name} 索引重建完成: {$total} 条";
        }

        $count = $service->rebuildIndex($type, $tenant->id);
        return $silent ? "{$type}: {$count} 条" : "租户 {$tenant->name} {$type} 索引重建完成: {$count} 条";
    }
}
