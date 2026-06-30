<?php

namespace App\Console\Commands;

use App\Services\AirGappedDeploymentService;
use Illuminate\Console\Command;

/**
 * M3-61 气隙部署状态检查命令
 *
 * 用法:
 *   php artisan air-gapped:status
 *   php artisan air-gapped:status --json
 */
class AirGappedStatusCommand extends Command
{
    protected $signature = 'air-gapped:status
        {--json : 以 JSON 格式输出}';

    protected $description = 'M3-61 查看气隙部署状态';

    public function handle(AirGappedDeploymentService $service): int
    {
        $status = $service->getStatus();
        $metrics = $service->getMetrics();

        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => $status,
                'metrics' => $metrics,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        $this->info('🔒 气隙部署状态');
        $this->newLine();

        $this->components->twoColumnDetail('气隙模式', $status['is_air_gapped'] ? '✅ 已启用' : '❌ 未启用');
        $this->components->twoColumnDetail('PHP版本', ($status['checks']['php_version'] ?? 'unknown'));
        $this->components->twoColumnDetail('扩展检查', $status['checks']['extensions'] ? '✅ 通过' : '❌ 缺失');
        $this->components->twoColumnDetail('存储可写', $status['checks']['storage_writable'] ? '✅ 可写' : '❌ 不可写');
        $this->components->twoColumnDetail('已导入License', (string) ($metrics['imported_licenses'] ?? 0));
        $this->components->twoColumnDetail('可用更新包', (string) ($metrics['available_updates'] ?? 0));
        $this->components->twoColumnDetail('离线包数量', (string) ($metrics['offline_packages_count'] ?? 0));
        $this->components->twoColumnDetail('占用空间', ($metrics['total_size_mb'] ?? 0) . ' MB');
        $this->components->twoColumnDetail('最后License导入', $metrics['last_import_time'] ?? '从未');
        $this->components->twoColumnDetail('最后更新包', $metrics['last_update_time'] ?? '从未');

        if (!$status['is_air_gapped']) {
            $this->newLine();
            $this->warn('⚠️  当前环境未处于气隙模式。如需启用，设置环境变量 AIR_GAPPED_MODE=true');
        }

        return Command::SUCCESS;
    }
}
