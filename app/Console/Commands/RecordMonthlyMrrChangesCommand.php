<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\MrrWaterfallService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * M3-59 记录月度MRR变化命令
 *
 * 扫描所有租户当月的订阅变动（新增/取消），自动写入 MrrChangeDetail 表。
 * 建议每月1日凌晨执行。
 *
 * 用法:
 *   php artisan mrr:record-changes
 *   php artisan mrr:record-changes --tenant=1
 *   php artisan mrr:record-changes --year-month=2026-06 --dry-run
 */
class RecordMonthlyMrrChangesCommand extends Command
{
    protected $signature = 'mrr:record-changes
        {--tenant= : 指定租户ID}
        {--year-month= : 指定年月 (YYYY-MM)}
        {--dry-run : 仅统计不写入}';

    protected $description = 'M3-59 扫描订阅变动并记录MRR变化';

    public function handle(MrrWaterfallService $mrrService): int
    {
        $yearMonth = $this->option('year-month') ?? now()->format('Y-m');
        $dryRun = $this->option('dry-run');
        $totalRecorded = 0;

        $this->info('📊 MRR月度变化记录');
        $this->line("期间: {$yearMonth}" . ($dryRun ? ' [干运行]' : ''));

        // 获取要处理的租户
        $tenantId = $this->option('tenant');
        if ($tenantId) {
            $tenants = Tenant::where('id', $tenantId)->get();
        } else {
            $tenants = Tenant::all();
        }

        foreach ($tenants as $tenant) {
            if ($dryRun) {
                // 干运行模式：统计但不写入
                $startOfMonth = \Carbon\Carbon::parse($yearMonth . '-01')->startOfMonth();
                $endOfMonth = $startOfMonth->copy()->endOfMonth();

                $newCount = \App\Models\Subscription::where('tenant_id', $tenant->id)
                    ->whereDate('starts_at', '>=', $startOfMonth)
                    ->whereDate('starts_at', '<=', $endOfMonth)
                    ->count();

                $cancelledCount = \App\Models\Subscription::where('tenant_id', $tenant->id)
                    ->whereIn('status', ['cancelled', 'expired'])
                    ->whereDate('ends_at', '>=', $startOfMonth)
                    ->whereDate('ends_at', '<=', $endOfMonth)
                    ->count();

                $this->line("  租户 #{$tenant->id}: {$newCount}新增, {$cancelledCount}取消 (干运行)");
                $totalRecorded += $newCount + $cancelledCount;
            } else {
                $result = $mrrService->scanAndRecordMonthlyChanges($tenant->id, $yearMonth);
                $this->line("  租户 #{$tenant->id}: 已记录 {$result['recorded']} 条");
                $totalRecorded += $result['recorded'];
            }
        }

        $this->newLine();
        $this->info("✅ 完成: {$totalRecorded} 条MRR变化" . ($dryRun ? ' (模拟)' : ''));

        Log::info('MrrChanges: recorded', [
            'year_month' => $yearMonth,
            'total' => $totalRecorded,
            'dry_run' => $dryRun,
        ]);

        return Command::SUCCESS;
    }
}
