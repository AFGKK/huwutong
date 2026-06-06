<?php

namespace App\Console\Commands;

use App\Services\ApiVersionManagerService;
use Illuminate\Console\Command;

class ProcessExpiredApiVersions extends Command
{
    protected $signature = 'hwt:process-expired-api-versions';
    protected $description = '处理过期的 API 版本（废弃超 6 个月自动停用，停用超 30 天自动退役）';

    public function handle(ApiVersionManagerService $versionManager): int
    {
        $this->info('Processing expired API versions...');

        $processed = $versionManager->processExpiredDeprecations();

        if (empty($processed)) {
            $this->info('No expired versions to process.');
        } else {
            foreach ($processed as $item) {
                $this->warn("Processed: {$item}");
            }
        }

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
