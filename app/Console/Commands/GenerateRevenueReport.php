<?php

namespace App\Console\Commands;

use App\Models\RevenueRecognitionSchedule;
use App\Services\RevenueRecognitionService;
use Illuminate\Console\Command;

class GenerateRevenueReport extends Command
{
    protected $signature = 'revenue:report
        {--tenant= : 指定租户ID（默认1）}
        {--year= : 报告年份}
        {--month= : 报告月份}
        {--snapshots : 同时生成月度快照}
        {--json : 输出JSON格式}';

    protected $description = '生成 ASC 606 收入确认报告';

    public function handle(RevenueRecognitionService $service): int
    {
        $tenantId = (int) ($this->option('tenant') ?? 1);
        $year = $this->option('year') ?? now()->format('Y');
        $month = $this->option('month') ?? now()->format('m');

        $this->info("ASC 606 Revenue Report — {$year}-{$month} (Tenant: {$tenantId})");

        // 生成月度快照
        if ($this->option('snapshots')) {
            $this->info('Generating monthly snapshot...');
            $service->generateMonthlySnapshot($tenantId, "{$year}-{$month}");
        }

        // 报告
        $report = $service->generateASC606Report($tenantId, $year, $month);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('=== Summary ===');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Period', $report['report_period']],
                ['Opening Deferred Revenue', '¥' . number_format($report['opening_deferred_revenue'], 2)],
                ['Total Invoiced', '¥' . number_format($report['total_invoiced'], 2)],
                ['Recognized Revenue', '¥' . number_format($report['recognized_revenue'], 2)],
                ['Change in Deferred', '¥' . number_format($report['change_in_deferred'], 2)],
                ['Closing Deferred Revenue', '¥' . number_format($report['closing_deferred_revenue'], 2)],
                ['New Schedules', "{$report['new_schedules_count']} (¥" . number_format($report['new_schedules_value'], 2) . ')'],
            ]
        );

        if (!empty($report['monthly_snapshot'])) {
            $snap = $report['monthly_snapshot'];
            $this->newLine();
            $this->info('=== Monthly Snapshot ===');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Invoiced Revenue', '¥' . number_format($snap->invoiced_revenue, 2)],
                    ['Recognized Revenue', '¥' . number_format($snap->recognized_revenue, 2)],
                    ['Deferred Revenue', '¥' . number_format($snap->deferred_revenue, 2)],
                    ['Refunds', '¥' . number_format($snap->refunds, 2)],
                    ['Net New ARR', '¥' . number_format($snap->net_new_arr, 2)],
                    ['Churned ARR', '¥' . number_format($snap->churned_arr, 2)],
                    ['Active Subscriptions', (string) $snap->active_subscriptions],
                ]
            );
        }

        if (count($report['recognized_transactions']) > 0) {
            $this->newLine();
            $this->info('=== Recognized Transactions ===');
            $this->table(
                ['Schedule', 'Period', 'Amount', 'Date', 'Invoice'],
                collect($report['recognized_transactions'])->map(fn($t) => [
                    $t['schedule_id'],
                    $t['period'],
                    '¥' . number_format($t['amount'], 2),
                    $t['recognition_date'],
                    $t['invoice_no'] ?? '-',
                ])->toArray()
            );
        }

        return self::SUCCESS;
    }
}
