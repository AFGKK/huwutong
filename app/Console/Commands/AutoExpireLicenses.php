<?php

namespace App\Console\Commands;

use App\Jobs\AutoExpireBulkJob;
use Illuminate\Console\Command;

class AutoExpireLicenses extends Command
{
    protected $signature = 'hwt:auto-expire-licenses
        {--tenant= : 限定特定租户 ID}';

    protected $description = '自动过期所有到期的 License（调度队列任务执行）';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $tenantId = $tenantId ? (int) $tenantId : null;

        $this->info('调度自动过期任务...');

        AutoExpireBulkJob::dispatch($tenantId);

        $this->info('AutoExpireBulkJob 已入队列');

        return Command::SUCCESS;
    }
}
