<?php

namespace App\Console\Commands;

use App\Services\RevenueRecognitionService;
use Illuminate\Console\Command;

class ProcessRevenueRecognition extends Command
{
    protected $signature = 'revenue:recognize
        {--tenant= : 指定租户ID}
        {--date= : 按指定日期确认（默认今天）}
        {--dry-run : 仅预览不实际确认}';

    protected $description = '执行收入确认（ASC 606/IFRS 15）— 确认当期应确认的递延收入';

    public function handle(RevenueRecognitionService $service): int
    {
        $date = $this->option('date') ? \Carbon\Carbon::parse($this->option('date')) : now();
        $dryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $this->info("Revenue Recognition — {$date->toDateString()}" . ($dryRun ? ' (DRY RUN)' : ''));

        if ($dryRun) {
            // 预览待确认的行
            $pendingLines = \App\Models\RevenueRecognitionLine::where('status', 'pending')
                ->whereDate('recognition_date', '<=', $date->toDateString())
                ->with('schedule')
                ->get();

            $totalAmount = $pendingLines->sum('amount');
            $this->table(
                ['Schedule ID', 'Period', 'Amount', 'Date', 'Invoice'],
                $pendingLines->map(fn($l) => [
                    $l->schedule_id,
                    $l->period_number,
                    number_format((float) $l->amount, 2),
                    $l->recognition_date,
                    $l->schedule?->invoice?->invoice_no ?? '-',
                ])
            );
            $this->info("Total to recognize: {$pendingLines->count()} lines, ¥" . number_format($totalAmount, 2));

            return self::SUCCESS;
        }

        // 执行确认
        $result = $service->processRecognition($date);

        $this->info("Recognized: {$result['recognized_count']} lines");
        $this->info("Total amount: ¥" . number_format($result['total_amount'], 2));

        if (!empty($result['details'])) {
            $this->table(
                ['Schedule ID', 'Period', 'Amount'],
                collect($result['details'])->map(fn($d) => [
                    $d['schedule_id'],
                    $d['period'],
                    number_format($d['amount'], 2),
                ])
            );
        }

        $deferred = $service->getDeferredRevenue($date, $tenantId);
        $this->info("Remaining deferred revenue: ¥" . number_format($deferred, 2));

        return self::SUCCESS;
    }
}
