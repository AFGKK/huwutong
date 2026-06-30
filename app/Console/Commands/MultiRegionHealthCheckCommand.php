<?php

namespace App\Console\Commands;

use App\Services\MultiRegionService;
use Illuminate\Console\Command;

/**
 * M2-37 🗄️ 多区域健康检查命令
 *
 * 对所有已配置的数据中心执行健康检查。
 * 用于灾备切换前的状态确认。
 *
 * 用法:
 *   php artisan multi-region:health-check           — 全部检查
 *   php artisan multi-region:health-check --dc=1    — 指定数据中心
 */
class MultiRegionHealthCheckCommand extends Command
{
    protected $signature = 'multi-region:health-check
        {--dc= : 指定数据中心 ID}
        {--all : 检查所有数据中心（默认）}';

    protected $description = 'M2-37 执行多数据中心健康检查';

    public function handle(MultiRegionService $multiRegion): int
    {
        $dcId = $this->option('dc');

        $this->info('🏥 多数据中心健康检查');
        $this->newLine();

        if ($dcId) {
            $dc = \App\Models\DataCenter::find($dcId);
            if (!$dc) {
                $this->error("数据中心 #{$dcId} 不存在");
                return Command::FAILURE;
            }
            $this->components->task("检查 {$dc->name} ({$dc->code})", function () use ($multiRegion, $dc) {
                $log = $multiRegion->performHealthCheck($dc);
                return $log->is_healthy;
            });
        } else {
            $dcs = \App\Models\DataCenter::active()->get();
            if ($dcs->isEmpty()) {
                $this->warn('没有活跃的数据中心，请先执行 php artisan multi-region:seed');
                return Command::SUCCESS;
            }

            $results = [];
            foreach ($dcs as $dc) {
                $ok = false;
                $this->components->task("检查 {$dc->name} ({$dc->code})", function () use ($multiRegion, $dc, &$ok) {
                    $log = $multiRegion->performHealthCheck($dc);
                    $ok = $log->is_healthy;
                    return $ok;
                });
                $results[] = [$dc->name, $dc->code, $ok ? '✅ 健康' : '❌ 异常', $dc->current_latency_ms ?? '-' . 'ms'];
            }

            $this->newLine();
            $this->table(['数据中心', '代码', '状态', '延迟'], $results);

            $allHealthy = collect($results)->every(fn($r) => str_contains($r[2], '✅'));
            if ($allHealthy) {
                $this->info('✅ 所有数据中心健康');
            } else {
                $this->warn('⚠️  部分数据中心异常，请检查');
            }
        }

        return Command::SUCCESS;
    }
}
