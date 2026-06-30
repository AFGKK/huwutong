<?php

namespace App\Workflows\Steps;

use App\Models\CommissionPayout;
use App\Models\WorkflowInstance;
use App\Workflows\BaseWorkflowStep;

/**
 * 佣金结算步骤3: 提现审批
 *
 * 大额提现需要人工审批，审批通过后才能执行实际打款。
 */
class ApprovePayout extends BaseWorkflowStep
{
    public function name(): string
    {
        return 'approve_payout';
    }

    public function description(): string
    {
        return '提现审批（大额需人工审核）';
    }

    public function execute(WorkflowInstance $instance, array &$context, array $input = []): array
    {
        $payoutId = $context['payout_id'] ?? $input['payout_id'] ?? null;
        if (! $payoutId) {
            throw new \RuntimeException('缺少 payout_id');
        }

        $payout = CommissionPayout::findOrFail($payoutId);
        $amount = (float) ($payout->amount ?? 0);
        $highThreshold = 5000; // 大额阈值

        $context['payout_amount'] = $amount;
        $context['needs_review'] = $amount >= $highThreshold;

        $this->log('info', '提现审批检查', [
            'payout_id' => $payout->id,
            'amount' => $amount,
            'needs_review' => $amount >= $highThreshold,
        ]);

        return [
            'payout_id' => $payout->id,
            'amount' => $amount,
            'needs_review' => $amount >= $highThreshold,
            'auto_approved' => $amount < $highThreshold,
        ];
    }

    public function compensate(WorkflowInstance $instance, array &$context, array $input, array $output): void
    {
        $payoutId = $output['payout_id'] ?? null;
        if ($payoutId) {
            $payout = CommissionPayout::find($payoutId);
            if ($payout && $payout->status === 'pending') {
                $payout->update(['status' => 'cancelled', 'notes' => '提现工作流失败，已取消']);
                $this->log('info', '提现已取消(补偿)', ['payout_id' => $payoutId]);
            }
        }
    }

    public function maxRetries(): int
    {
        return 1;
    }

    public function retryDelay(): array|int
    {
        return [60];
    }
}
