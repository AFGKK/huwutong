<?php

namespace App\Console\Commands;

use App\Services\SecretScanService;
use Illuminate\Console\Command;

class SecretScanCommand extends Command
{
    /**
     * 签名: hwt:secret-scan
     *
     * 全量扫描: php artisan hwt:secret-scan
     * 快速扫描: php artisan hwt:secret-scan --quick
     * 干跑模式: php artisan hwt:secret-scan --dry-run
     */
    protected $signature = 'hwt:secret-scan
                          {--quick : 仅扫描最近修改的文件}
                          {--dry-run : 干跑模式，仅输出结果不告警}
                          {--batch= : 每次扫描的文件数}';

    protected $description = 'M1.3-29 密钥泄露扫描 - 检查项目中硬编码的密钥';

    public function handle(SecretScanService $scanService): int
    {
        $this->info('🔒 开始密钥泄露扫描...');

        $result = $this->option('quick')
            ? $scanService->quickScan()
            : $scanService->scan();

        $this->newLine();
        $this->line("   扫描文件: {$result['scanned']}");
        $this->line("   发现泄露: {$result['total_findings']}");
        $this->newLine();

        if (empty($result['leaks'])) {
            $this->info('  ✅ 未检测到密钥泄露');
            return Command::SUCCESS;
        }

        // 按文件分组展示
        $grouped = collect($result['leaks'])->groupBy('file');
        foreach ($grouped as $file => $leaks) {
            $this->warn("  ❌ {$file}");
            foreach ($leaks as $leak) {
                $this->line("     - [{$leak['severity']}] {$leak['pattern']}: {$leak['matched']}");
            }
        }

        $this->newLine();

        if ($result['total_findings'] > 0 && !$this->option('dry-run')) {
            $processed = $scanService->processLeaks($result);
            $this->info("   告警/处置: {$processed['processed']} 条");
        }

        $this->newLine();
        $this->warn('  ⚠️  请及时处理上述密钥泄露');

        return Command::SUCCESS;
    }
}
