<?php

namespace App\Console\Commands;

use App\Models\CreditLimit;
use App\Models\PrepaidTransaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * M3-56 信用额度自动评估命令
 *
 * 根据客户消费行为自动调整信用额度。
 * 仅当 config('prepaid.credit.auto_increase_enabled') 为 true 时生效。
 * 建议每月执行一次。
 *
 * 用法:
 *   php artisan prepaid:credit-assessment           — 执行评估
 *   php artisan prepaid:credit-assessment --dry-run — 预览模式
 */
class CreditAutoAssessmentCommand extends Command
{
    protected $signature = 'prepaid:credit-assessment
        {--dry-run : 仅统计不执行调整}';

    protected $description = 'M3-56 信用额度自动评估与调整';

    public function handle(): int
    {
        if (!config('prepaid.credit.auto_increase_enabled', false)) {
            $this->warn('⚠️ 信用额度自动提升功能未启用 (config/prepaid.php credit.auto_increase_enabled = false)');
            return Command::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $this->info('📊 信用额度自动评估');

        $assessed = 0;
        $increased = 0;
        $decreased = 0;

        // 获取所有活跃信用额度
        $credits = CreditLimit::where('status', 'active')->get();

        foreach ($credits as $credit) {
            $assessed++;

            // 计算最近90天的消费总额
            $recentConsumption = (float) PrepaidTransaction::where('customer_id', $credit->customer_id)
                ->where('type', 'consume')
                ->where('status', 'completed')
                ->where('created_at', '>=', now()->subDays(90))
                ->sum(DB::raw('ABS(amount)'));

            $monthlyAvgConsumption = $recentConsumption / 3; // 近3个月月均消费

            $currentLimit = (float) $credit->credit_limit;
            $newLimit = $currentLimit;

            // 如果月均消费显著高于当前额度(>2倍)，自动提升
            if ($monthlyAvgConsumption > $currentLimit * 2 && $monthlyAvgConsumption > 0) {
                $newLimit = min(
                    (int) round($monthlyAvgConsumption * 1.5 / 100) * 100, // 向上取整到百
                    (int) config('prepaid.credit.max_limit', 1000000)
                );
            }

            // 如果长期(6个月+)未使用额度，自动降低
            if ($recentConsumption <= 0 && $currentLimit > 1000) {
                $newLimit = max(1000, (int) ($currentLimit * 0.5));
            }

            if ($newLimit !== $currentLimit) {
                if ($dryRun) {
                    $change = $newLimit > $currentLimit ? '提升' : '降低';
                    $this->line("  [干运行] 客户 #{$credit->customer_id} 额度 {$currentLimit}→{$newLimit} ({$change})");
                } else {
                    $credit->update([
                        'credit_limit' => $newLimit,
                        'last_assessment_at' => now(),
                    ]);

                    if ($newLimit > $currentLimit) $increased++;
                    else $decreased++;

                    $this->line("  ✅ 客户 #{$credit->customer_id} 额度调整: {$currentLimit} → {$newLimit}");
                }
            }

            // 更新评估时间
            if (!$dryRun) {
                $credit->update(['last_assessment_at' => now()]);
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("📋 [干运行] 评估 {$assessed} 个账户，{$increased} 提升，{$decreased} 降低");
        } else {
            $this->info("📋 评估完成: {$assessed} 个账户，{$increased} 提升，{$decreased} 降低");
        }

        Log::info('CreditAutoAssessment: completed', [
            'assessed' => $assessed,
            'increased' => $increased,
            'decreased' => $decreased,
            'dry_run' => $dryRun,
        ]);

        return Command::SUCCESS;
    }
}
