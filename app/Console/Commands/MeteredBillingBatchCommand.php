<?php

namespace App\Console\Commands;

use App\Services\MeteredBillingService;
use Illuminate\Console\Command;

class MeteredBillingBatchCommand extends Command
{
    protected $signature = 'metered:billing:batch
        {--billing-period=monthly : 结算周期 (monthly/quarterly/yearly)}
        {--dry-run : 试算模式，不生成发票}
        {--notify : 完成后发送通知}';
    protected $description = '批量生成所有已启用用量计费的订阅账单（M3-76 🔢）';

    public function handle(MeteredBillingService $meteredBillingService): int
    {
        $billingPeriod = $this->option('billing-period');
        $dryRun = $this->option('dry-run');

        $this->info("开始批量{$billingPeriod}用量计费结算" . ($dryRun ? '（试算模式）' : ''));

        $result = $meteredBillingService->batchGenerateMeteredInvoices($billingPeriod, $dryRun);

        $this->info("处理完成：共 {$result['total']} 个订阅，成功 {$result['success']} 个");
        $this->getOutput()->writeln(json_encode([
            'total' => $result['total'],
            'success' => $result['success'],
            'dry_run' => $dryRun,
            'billing_period' => $billingPeriod,
        ]));

        if ($this->option('notify') && $result['success'] > 0) {
            // 可扩展：发送通知给管理员
            $this->info("将发送通知给管理员");
        }

        return Command::SUCCESS;
    }
}
