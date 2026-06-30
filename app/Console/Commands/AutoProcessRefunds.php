<?php

namespace App\Console\Commands;

use App\Models\Refund;
use App\Models\RefundRiskAssessment;
use App\Services\RefundEngineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoProcessRefunds extends Command
{
    protected $signature = 'refund:auto-process
        {--dry-run : 仅预览不执行}
        {--limit=50 : 每次处理上限}';

    protected $description = '自动处理待审核退款（风控决策+超时升级）';

    public function handle(RefundEngineService $refundEngine): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');
        $processed = 0;
        $results = [
            'assessed' => 0,
            'auto_approved' => 0,
            'auto_rejected' => 0,
            'require_review' => 0,
            'errors' => 0,
        ];

        // 1. 处理未评估的退款
        $unassessed = Refund::whereNull('risk_assessment_id')
            ->whereIn('status', ['pending', 'processing'])
            ->limit($limit)
            ->get();

        foreach ($unassessed as $refund) {
            if ($dryRun) {
                $this->line("  [DRY-RUN] 将评估退款 #{$refund->id} ({$refund->refund_no})");
                $results['assessed']++;
                continue;
            }

            try {
                $assessment = $refundEngine->assess($refund);
                $decision = $refundEngine->executeDecision($refund);
                $processed++;

                $action = $decision['action'] ?? 'unknown';
                $this->info("  退款 #{$refund->id}: 风险分={$assessment->risk_score} 等级={$assessment->risk_level} 决策={$action}");

                match ($action) {
                    'approved' => $results['auto_approved']++,
                    'rejected' => $results['auto_rejected']++,
                    default => $results['require_review']++,
                };
            } catch (\Exception $e) {
                $results['errors']++;
                Log::error('自动退款处理失败', [
                    'refund_id' => $refund->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  退款 #{$refund->id} 处理失败: {$e->getMessage()}");
            }
        }

        // 2. 处理超时未审核的退款（>24h）
        $maxMinutes = config('refund-engine.auto_processing.max_pending_minutes', 1440);
        $overdueAssessments = RefundRiskAssessment::where('review_status', 'pending')
            ->where('decision', 'require_review')
            ->where('created_at', '<=', now()->subMinutes($maxMinutes))
            ->limit($limit)
            ->get();

        foreach ($overdueAssessments as $assessment) {
            $refund = $assessment->assessable;
            if (!$refund) continue;

            if ($dryRun) {
                $this->line("  [DRY-RUN] 将自动批准超时退款 #{$refund->id}");
                continue;
            }

            try {
                // 超时自动批准
                $refundEngine->review($refund, 'approve', 1, '系统自动批准（超过处理时限）');
                $results['auto_approved']++;
                $processed++;
                $this->info("  超时退款 #{$refund->id} 已自动批准");
            } catch (\Exception $e) {
                $results['errors']++;
                Log::error('超时退款自动批准失败', [
                    'refund_id' => $refund->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 输出汇总
        $this->newLine();
        $this->table(
            ['指标', '数量'],
            [
                ['已评估', $results['assessed']],
                ['自动批准', $results['auto_approved']],
                ['自动拒绝', $results['auto_rejected']],
                ['需人工审核', $results['require_review']],
                ['处理失败', $results['errors']],
                ['合计处理', $processed],
            ]
        );

        if ($dryRun) {
            $this->warn('本次为 DRY-RUN，未实际执行任何操作');
        }

        return Command::SUCCESS;
    }
}
