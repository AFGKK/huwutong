<?php

namespace App\Console\Commands;

use App\Services\AirGappedDeploymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * M3-61 气隙部署健康检查命令
 *
 * 检查 PHP 扩展、存储空间、Docker 状态等。
 * 可用于定时监控。
 *
 * 用法:
 *   php artisan air-gapped:health
 *   php artisan air-gapped:health --notify
 */
class AirGappedHealthCheckCommand extends Command
{
    protected $signature = 'air-gapped:health
        {--notify : 异常时记录日志告警}';

    protected $description = 'M3-61 气隙部署健康检查';

    public function handle(AirGappedDeploymentService $service): int
    {
        $this->info('🏥 气隙部署健康检查');

        $status = $service->getStatus();
        $checks = $status['checks'] ?? [];
        $allPassed = true;

        $this->newLine();
        $this->line(' PHP 版本: ' . ($checks['php_version'] ?? 'unknown'));

        // 扩展检查
        $extensionsOk = $checks['extensions'] ?? false;
        $this->line(' PHP 扩展: ' . ($extensionsOk ? '✅' : '❌'));
        if (!$extensionsOk) {
            $allPassed = false;
        }

        // 存储检查
        $storageOk = $checks['storage_writable'] ?? false;
        $this->line(' 存储可写: ' . ($storageOk ? '✅' : '❌'));
        if (!$storageOk) {
            $allPassed = false;
        }

        // Docker 检查
        try {
            $dockerInfo = $service->getDockerInfo();
            $this->line(' Docker: ' . ($dockerInfo['docker_available'] ? '✅ ' . ($dockerInfo['docker_version'] ?? '') : '❌ 不可用'));
            if (!$dockerInfo['docker_available']) {
                $allPassed = false;
            }
        } catch (\Exception $e) {
            $this->line(' Docker: ❌ ' . $e->getMessage());
            $allPassed = false;
        }

        $this->newLine();

        if ($allPassed) {
            $this->info('✅ 所有健康检查通过');
        } else {
            $this->warn('⚠️  部分检查未通过');

            if ($this->option('notify')) {
                Log::warning('AirGapped: health check failed', [
                    'checks' => $checks,
                ]);
            }
        }

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }
}
