<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\RevenueRecognitionService;
use Illuminate\Console\Command;

class CreateRevenueSchedules extends Command
{
    protected $signature = 'revenue:create-schedules
        {--tenant= : 指定租户ID（默认1）}
        {--invoice= : 仅处理指定发票ID（逗号分隔）}
        {--dry-run : 仅预览不实际创建}';

    protected $description = '为已支付的发票创建收入确认排程（追认）';

    public function handle(RevenueRecognitionService $service): int
    {
        $tenantId = (int) ($this->option('tenant') ?? 1);
        $dryRun = $this->option('dry-run');
        $invoiceIds = $this->option('invoice')
            ? array_map('intval', explode(',', $this->option('invoice')))
            : null;

        $query = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereDoesntHave('revenueSchedule');

        if ($invoiceIds) {
            $query->whereIn('id', $invoiceIds);
        }

        $candidates = $query->get();
        $this->info("Found {$candidates->count()} invoices without schedules");

        if ($dryRun) {
            $this->table(
                ['Invoice ID', 'Invoice No', 'Amount', 'Paid At', 'Reason'],
                $candidates->map(fn($inv) => [
                    $inv->id,
                    $inv->invoice_no,
                    '¥' . number_format((float) $inv->amount, 2),
                    $inv->paid_at?->toDateString(),
                    $inv->billing_reason,
                ])
            );
            return self::SUCCESS;
        }

        $result = $service->createSchedulesForExistingInvoices($tenantId, $invoiceIds);
        $this->info("Created {$result['created']} schedules (of {$result['total_candidates']} candidates)");

        return self::SUCCESS;
    }
}
