<?php

namespace App\Console\Commands;

use App\Services\StaticAssetCdnService;
use Illuminate\Console\Command;

class ActivateCdnVersion extends Command
{
    protected $signature = 'assets:cdn-activate
        {version? : 要激活的版本号（默认使用最新部署版本）}';

    protected $description = '激活指定版本的 CDN 静态资源 (M2-133)';

    public function handle(StaticAssetCdnService $cdnService): int
    {
        $version = $this->argument('version');

        try {
            $result = $cdnService->activateVersion($version);

            $this->info("已激活版本: {$result['version']}");
            $this->line("CDN Base URL: {$result['base_url']}");

            return Command::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
