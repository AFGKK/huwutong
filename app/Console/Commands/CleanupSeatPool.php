<?php

namespace App\Console\Commands;

use App\Services\SeatPoolService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupSeatPool extends Command
{
    protected $signature = 'hwt:cleanup-seat-pool
        {--tenant= : 限定特定租户 ID}
        {--dry-run : 仅预览不执行}';

    protected $description = '清理过期席位和排队超时（M2-91 并发License Floating）';

    public function handle(SeatPoolService $seatPool): int
    {
        $tenantId = $this->option('tenant');
        $tenantId = $tenantId ? (int) $tenantId : null;
        $dryRun = (bool) $this->option('dry-run');

        $this->info('开始清理席位池...');

        // 1. 清理过期席位
        $expiredCount = 0;
        $licenseResults = [];

        // 获取所有启用了席位池的 License
        $licensesQuery = \App\Models\License::whereNotNull('seats')->where('seats', '>', 0);
        if ($tenantId) {
            $licensesQuery->where('tenant_id', $tenantId);
        }

        $licenses = $licensesQuery->get();
        $this->info("共发现 {$licenses->count()} 个启用席位池的 License");

        foreach ($licenses as $license) {
            if ($dryRun) {
                $activeCount = \App\Models\SeatAssignment::where('license_id', $license->id)
                    ->where('status', 'active')
                    ->where('last_active_at', '<', Carbon::now()->subMinutes($license->pool_timeout_minutes ?? 30))
                    ->count();
                if ($activeCount > 0) {
                    $this->line("  [DRY-RUN] License #{$license->id} ({$license->license_key}): 将释放 {$activeCount} 个过期席位");
                }
                continue;
            }

            $released = $seatPool->releaseExpiredSeats($license);
            if ($released > 0) {
                $expiredCount += $released;
                $licenseResults[] = [
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                    'released' => $released,
                ];
            }
        }

        // 2. 清理过期排队
        $expiredQueueCount = 0;
        $expiredQueueEntries = \App\Models\SeatWaitingQueue::where('status', 'waiting')
            ->where('expires_at', '<', Carbon::now());

        if ($expiredQueueEntries->count() > 0) {
            if ($dryRun) {
                $this->line("  [DRY-RUN] 将过期 {$expiredQueueEntries->count()} 个排队条目");
            } else {
                $expiredQueueCount = $expiredQueueEntries->count();
                $expiredQueueEntries->update(['status' => 'expired']);
            }
        }

        // 3. 统计结果
        $totalProcessed = $expiredCount + $expiredQueueCount;

        if ($dryRun) {
            $this->table(
                ['指标', '值'],
                [
                    ['过期席位(待释放)', $this->getDryRunExpiredCount($licenses, $seatPool)],
                    ['过期排队(待清理)', $expiredQueueEntries->count()],
                ]
            );
            $this->warn('DRY-RUN 模式：未执行任何修改');
            return Command::SUCCESS;
        }

        $this->info("清理完成：释放 {$expiredCount} 个过期席位，过期 {$expiredQueueCount} 个排队");

        Log::channel('seatpool')->info('席位池定时清理', [
            'released_seats' => $expiredCount,
            'expired_queue' => $expiredQueueCount,
            'licenses_affected' => count($licenseResults),
            'tenant_id' => $tenantId,
        ]);

        // 输出表格
        $this->table(
            ['指标', '值'],
            [
                ['检查 License 数', $licenses->count()],
                ['释放过期席位', $expiredCount],
                ['过期排队条目', $expiredQueueCount],
                ['受影响 License', count($licenseResults)],
                ['总处理数', $totalProcessed],
            ]
        );

        return Command::SUCCESS;
    }

    private function getDryRunExpiredCount($licenses, SeatPoolService $seatPool): int
    {
        $count = 0;
        foreach ($licenses as $license) {
            $count += \App\Models\SeatAssignment::where('license_id', $license->id)
                ->where('status', 'active')
                ->where('last_active_at', '<', Carbon::now()->subMinutes($license->pool_timeout_minutes ?? 30))
                ->count();
        }
        return $count;
    }
}
