<?php

namespace App\Console\Commands;

use App\Models\PrepaidBalance;
use App\Services\PrepaidBalanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * M3-56 预付余额自动充值命令
 *
 * 扫描所有启用了自动充值且余额低于阈值的账户，自动触发充值。
 * 建议每60分钟执行一次。
 *
 * 用法:
 *   php artisan prepaid:auto-recharge           — 全部检查
 *   php artisan prepaid:auto-recharge --dry-run — 预览模式
 */
class PrepaidAutoRechargeCommand extends Command
{
    protected $signature = 'prepaid:auto-recharge
        {--dry-run : 仅统计不执行充值}';

    protected $description = 'M3-56 检查预付余额并自动触发充值';

    public function handle(PrepaidBalanceService $service): int
    {
        $dryRun = $this->option('dry-run');
        $this->info('🔋 预付余额自动充值检查');

        // 获取所有启用了自动充值且余额低于阈值的活跃账户
        $balances = PrepaidBalance::where('status', 'active')
            ->where('metadata->auto_recharge->enabled', true)
            ->get();

        $checked = 0;
        $triggered = 0;

        foreach ($balances as $balance) {
            $settings = $balance->metadata['auto_recharge'] ?? [];
            $threshold = (float) ($settings['threshold'] ?? config('prepaid.auto_recharge.default_threshold', 100));
            $amount = (float) ($settings['amount'] ?? config('prepaid.auto_recharge.default_amount', 500));

            if ((float) $balance->balance < $threshold) {
                $checked++;

                if ($dryRun) {
                    $this->line("  [干运行] 客户 #{$balance->customer_id} 余额 ¥{$balance->balance} < 阈值 ¥{$threshold}，将充值 ¥{$amount}");
                } else {
                    try {
                        $customer = $balance->customer;
                        if (!$customer) {
                            continue;
                        }
                        $service->recharge($customer, $amount, $settings['payment_method'] ?? 'alipay', 'CNY', '自动充值');
                        $triggered++;
                        $this->line("  ✅ 客户 #{$balance->customer_id} 已自动充值 ¥{$amount}");
                    } catch (\Exception $e) {
                        $this->error("  ❌ 客户 #{$balance->customer_id} 充值失败: {$e->getMessage()}");
                        Log::warning('Prepaid: auto-recharge failed', [
                            'customer_id' => $balance->customer_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("📋 [干运行] 发现 {$checked} 个待充值账户");
        } else {
            $this->info("📋 检查完成: {$triggered} 个账户已自动充值");
        }

        Log::info('PrepaidAutoRecharge: completed', [
            'checked' => $checked,
            'triggered' => $triggered,
            'dry_run' => $dryRun,
        ]);

        return Command::SUCCESS;
    }
}
