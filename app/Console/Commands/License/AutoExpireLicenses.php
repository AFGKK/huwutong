<?php

namespace App\Console\Commands\License;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Services\LicenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoExpireLicenses extends Command
{
    protected $signature = 'hwt:auto-expire-licenses
                            {--dry-run : 仅预览将要过期的 License，不执行过期操作}';

    protected $description = '自动过期已到期的 License';

    public function handle(LicenseService $licenseService): int
    {
        $expiredCount = 0;
        $dryRun = $this->option('dry-run');

        // 查找已过期但状态仍为 active/suspended/frozen 的 License
        $licenses = License::whereIn('status', [
                LicenseStatus::Active->value,
                LicenseStatus::Suspended->value,
                LicenseStatus::Frozen->value,
            ])
            ->where('expires_at', '<=', now())
            ->cursor();

        foreach ($licenses as $license) {
            if ($dryRun) {
                $this->line("  [DRY-RUN] License {$license->license_key} (ID: {$license->id}) 应该过期");
                $expiredCount++;
                continue;
            }

            try {
                $licenseService->expire($license, '系统自动过期');
                $expiredCount++;
            } catch (\Throwable $e) {
                Log::error('自动过期失败', [
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($dryRun) {
            $this->info("[DRY-RUN] 发现 {$expiredCount} 个将要过期的 License（未执行）");
        } else {
            $this->info("已自动过期 {$expiredCount} 个 License");
        }

        return Command::SUCCESS;
    }
}
