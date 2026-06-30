<?php

namespace App\Console\Commands;

use App\Services\CrlService;
use Illuminate\Console\Command;

class CrlAutoVerify extends Command
{
    protected $signature = 'hwt:crl-auto-verify
        {--batch=100 : 每批处理数量}
        {--dry-run : 仅预览不执行}';

    protected $description = '网络恢复后自动补全验证离线激活的 License 是否被吊销 (M1.3-03)';

    public function handle(CrlService $crlService): int
    {
        $batchSize = (int) $this->option('batch');
        $dryRun = (bool) $this->option('dry-run');

        $pendingCount = $crlService->getPendingAutoVerifyCount();

        if ($pendingCount === 0) {
            $this->info('没有待补全验证的离线记录');
            return Command::SUCCESS;
        }

        $this->info("发现 {$pendingCount} 条待补全验证的离线记录");

        if ($dryRun) {
            $this->warn("[DRY-RUN] 将处理 {$pendingCount} 条记录（批处理 {$batchSize}）");
            $this->table(['指标', '值'], [
                ['待处理数', $pendingCount],
                ['批处理大小', $batchSize],
            ]);
            return Command::SUCCESS;
        }

        $result = $crlService->autoCompleteVerification($batchSize);

        $this->info('网络恢复自动补全验证完成');
        $this->table(['指标', '值'], [
            ['已处理', $result['processed']],
            ['发现被吊销', $result['revoked_found']],
            ['正常', $result['processed'] - $result['revoked_found']],
        ]);

        if ($result['revoked_found'] > 0) {
            $this->warn('发现被吊销 License:');
            foreach ($result['revoked_keys'] as $key) {
                $this->line("  - {$key}");
            }
        }

        return Command::SUCCESS;
    }
}
