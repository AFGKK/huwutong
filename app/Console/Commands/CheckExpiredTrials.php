<?php

namespace App\Console\Commands;

use App\Services\TrialLicenseService;
use Illuminate\Console\Command;

class CheckExpiredTrials extends Command
{
    protected $signature = 'hwt:check-trials';
    protected $description = '检查并处理过期的 Trial License';

    public function handle(TrialLicenseService $trialService): int
    {
        $this->info('检查即将过期的 Trial...');
        $expiring = $trialService->checkExpiringTrials();
        $this->info("发现 {$expiring->count()} 个即将过期的 Trial");

        foreach ($expiring as $licenseId => $result) {
            $this->warn("  License #{$licenseId}: {$result['message']}");
        }

        $this->info('处理已过期的 Trial...');
        $expired = $trialService->expireOverdueTrials();
        $this->info("已将 {$expired->count()} 个过期 Trial 自动停用");

        foreach ($expired as $licenseId => $result) {
            $this->line("  License #{$licenseId} ({$result['license_key']}): {$result['action']}");
        }

        return Command::SUCCESS;
    }
}
