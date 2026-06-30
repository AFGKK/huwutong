<?php

namespace App\Workflows\Steps;

use App\Models\CommissionSettlement;
use App\Models\WorkflowInstance;
use App\Services\CommissionRiskGuard;
use App\Workflows\BaseWorkflowStep;

/**
 * 佣金结算步骤2: 冻结期满解冻 — T+30 到达后从 pending 转入 available
 */
class ReleaseCommission extends BaseWorkflowStep
{
    public function __construct(protected CommissionRiskGuard $riskGuard) {}

    public function name(): string
    {
        return 'release_commission';
    }

    public function description(): string
    {
        return 'T+30 冻结期满，解冻佣金至可用余额';
    }

    public function execute(WorkflowInstance $instance, array &$context, array $input = []): array
    {
        $settlementId = $context['settlement_id'] ?? $input['settlement_id'] ?? null;
        if (! $settlementId) {
            throw new \RuntimeException('缺少 settlement_id');
        }

        $settlement = CommissionSettlement::findOrFail($settlementId);

        if ($settlement->status !== 'pending') {
            $this->log('warning', '结算状态不是 pending，跳过解冻', [
                'settlement_id' => $settlement->id,
                'status' => $settlement->status,
            ]);
            return ['settlement_id' => $settlement->id, 'skipped' => true];
        }

        $this->riskGuard->releaseExpiredFreezes();

        $settlement->refresh();
        $context['released_at'] = now()->toIso8601String();
        $context['released_amount'] = $settlement->amount;

        $this->log('info', '佣金解冻完成', [
            'settlement_id' => $settlement->id,
            'amount' => $settlement->amount,
            'new_status' => $settlement->status,
        ]);

        return [
            'settlement_id' => $settlement->id,
            'released_at' => $context['released_at'],
            'amount' => $settlement->amount,
        ];
    }

    public function compensate(WorkflowInstance $instance, array &$context, array $input, array $output): void
    {
        // 解冻操作不可回滚，仅记录审计
        $this->log('warning', '佣金解冻无法回滚，需人工审核', [
            'instance_id' => $instance->id,
            'settlement_id' => $output['settlement_id'] ?? null,
        ]);
    }
}
