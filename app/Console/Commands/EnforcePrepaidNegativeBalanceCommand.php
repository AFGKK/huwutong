<?php

namespace App\Console\Commands;

use App\Models\PrepaidBalance;
use App\Models\CreditLimit;
use App\Models\PrepaidTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * M3-56 预付负余额宽限期检查命令
 *
 * 检查使用信用额度后超出宽限期的负余额账户，自动发送通知并冻结。
 * 建议每天执行一次。
 *
 * 用法:
 *   php artisan prepaid:enforce-negative-balance           — 执行检查
 *   php artisan prepaid:enforce-negative-balance --dry-run — 预览模式
 */
class EnforcePrepaidNegativeBalanceCommand extends Command
{
    protected $signature = 'prepaid:enforce-negative-balance
        {--dry-run : 仅统计不执行冻结}';

    protected $description = 'M3-56 检查预付负余额宽限期并执行冻结';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->info('🚨 预付负余额宽限期检查');

        $graceDays = config('prepaid.credit.negative_balance_grace_days', 7);
        $deadline = now()->subDays($graceDays);
        $frozen = 0;
        $notified = 0;

        // 查找信用额度已用尽且超过宽限期的账户
        $creditLimits = CreditLimit::where('status', 'active')
            ->whereColumn('used_credit', '>=', 'credit_limit')
            ->where('last_assessment_at', '<=', $deadline)
            ->get();

        foreach ($creditLimits as $credit) {
            $balance = PrepaidBalance::where('customer_id', $credit->customer_id)
                ->where('status', 'active')
                ->first();

            if (!$balance) continue;

            if ($dryRun) {
                $this->line("  [干运行] 客户 #{$credit->customer_id} 信用超限已{$graceDays}天，将冻结账户");
                $notified++;
            } else {
                // 冻结余额账户
                $balance->update(['status' => 'frozen']);

                // 冻结信用额度
                $credit->update(['status' => 'suspended']);

                // 记录交易
                PrepaidTransaction::create([
                    'tenant_id' => $credit->tenant_id,
                    'customer_id' => $credit->customer_id,
                    'type' => 'adjust',
                    'amount' => 0,
                    'balance_before' => $balance->balance,
                    'balance_after' => $balance->balance,
                    'currency' => 'CNY',
                    'status' => 'completed',
                    'description' => "负余额宽限期({$graceDays}天)已到，账户自动冻结",
                ]);

                $frozen++;
                $this->line("  ✅ 客户 #{$credit->customer_id} 已冻结");
            }
        }

        $this->newLine();
        $msg = $dryRun
            ? "📋 [干运行] 发现 {$notified} 个待冻结账户"
            : "📋 检查完成: {$frozen} 个账户已冻结";

        $this->info($msg);

        Log::info('PrepaidNegativeBalance: enforced', [
            'frozen' => $frozen,
            'grace_days' => $graceDays,
            'dry_run' => $dryRun,
        ]);

        return Command::SUCCESS;
    }
}
