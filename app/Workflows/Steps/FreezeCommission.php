<?php

namespace App\Workflows\Steps;

use App\Models\CommissionSettlement;
use App\Models\WorkflowInstance;
use App\Services\CommissionRiskGuard;
use App\Workflows\BaseWorkflowStep;

/**
 * 佣金结算步骤1: T+30 冻结
 *
 * 在佣金结算入账时创建冻结记录，进入 30 天退款保护期。
 */
class FreezeCommission extends BaseWorkflowStep
{
    public function __construct(protected CommissionRiskGuard $riskGuard) {}

    public function name(): string
    {
        return 'freeze_commission';
    }

    public function description(): string
    {
        return 'T+30 冻结佣金（退款保护期）';
    }

    public function execute(WorkflowInstance $instance, array &$context, array $input = []): array
    {
        $settlementId = $context['settlement_id'] ?? $input['settlement_id'] ?? null;
        if (! $settlementId) {
            throw new \RuntimeException('缺少 settlement_id');
        }

        $settlement = CommissionSettlement::findOrFail($settlementId);

        $this->log('info', '冻结佣金', [
            'settlement_id' => $settlement->id,
            'amount' => $settlement->amount,
            'release_at' => now()->addDays(30)->toDateString(),
        ]);

        $context['freeze_started_at'] = now()->toIso8601String();
        $context['freeze_period_days'] = 30;
        $context['expected_release_at'] = now()->addDays(30)->toIso8601String();

        return [
            'settlement_id' => $settlement->id,
            'frozen_at' => $context['freeze_started_at'],
            'expected_release_at' => $context['expected_release_at'],
        ];
    }

    public function compensate(WorkflowInstance $instance, array &$context, array $input, array $output): void
    {
        $settlementId = $output['settlement_id'] ?? null;
        if ($settlementId) {
            $settlement = CommissionSettlement::find($settlementId);
            if ($settlement && $settlement->status === 'pending') {
                $settlement->update(['status' => 'cancelled']);
                $this->log('info', '佣金冻结已取消(补偿)', ['settlement_id' => $settlementId]);
            }
        }
    }
}
