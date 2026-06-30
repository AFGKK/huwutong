<?php

namespace App\Console\Commands;

use App\Services\OfflineLicenseService;
use App\Services\PublicKeyVersionService;
use Illuminate\Console\Command;

class RotatePublicKeyVersion extends Command
{
    protected $signature = 'key-version:auto-rotate
                            {--force : 即使未到轮换阈值也强制轮换}
                            {--dry-run : 仅显示将要执行的操作}';

    protected $description = '检查并自动轮换公钥版本（到期前30天触发轮换）';

    public function handle(PublicKeyVersionService $keyVersionService, OfflineLicenseService $offlineService): int
    {
        $this->info('检查公钥版本轮换状态...');

        $check = $keyVersionService->checkRotationNeeded();

        if (! $check['needed'] && ! $this->option('force')) {
            $this->info("当前公钥版本 v{$check['key_version']} 无需轮换");
            $this->line("  有效期至: {$check['expires_at']}");
            $this->line("  剩余天数: {$check['days_until_expiry']}");
            $this->line("  轮换阈值: {$check['threshold_days']} 天");

            return Command::SUCCESS;
        }

        $reason = $check['reason'] ?? '定期轮换';

        if ($this->option('dry-run')) {
            $this->warn("[DRY-RUN] 将执行公钥轮换：");
            $this->line("  原因: {$reason}");
            $this->line("  当前版本: v{$check['key_version']}");
            $this->line("  新版本: v" . (($check['key_version'] ?? 0) + 1));
            $this->line("  兼容窗口: " . PublicKeyVersionService::DEFAULT_COMPAT_WINDOW_DAYS . " 天");

            return Command::SUCCESS;
        }

        $this->warn("执行公钥轮换: {$reason}");

        // 生成新密钥对
        $keyPair = $offlineService->generateKeyPair();

        $this->line("新公钥: {$keyPair['public_key']}");

        // 创建新版本
        $version = $keyVersionService->createVersion(
            $keyPair['public_key'],
            OfflineLicenseService::ALGORITHM_ED25519,
        );

        $this->info("公钥版本 v{$version->key_version} 已创建并激活");
        $this->line("有效期至: {$version->expires_at->toDateString()}");
        $this->line("旧版本保持 " . PublicKeyVersionService::DEFAULT_COMPAT_WINDOW_DAYS . " 天兼容窗口");

        return Command::SUCCESS;
    }
}
