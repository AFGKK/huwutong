<?php

namespace App\Workflows\Steps;

use App\Models\License;
use App\Models\Subscription;
use App\Models\WorkflowInstance;
use App\Workflows\BaseWorkflowStep;

/**
 * 步骤4: 延长关联License
 */
class ExtendLicenses extends BaseWorkflowStep
{
    public function name(): string
    {
        return 'extend_licenses';
    }

    public function description(): string
    {
        return '延长关联 License 有效期';
    }

    public function execute(WorkflowInstance $instance, array &$context, array $input = []): array
    {
        /** @var Subscription $subscription */
        $subscription = $instance->workflowable;
        if (!$subscription) {
            throw new \RuntimeException('工作流未关联 Subscription');
        }

        $licenses = License::where('subscription_id', $subscription->id)->get();
        $extendedCount = 0;

        foreach ($licenses as $license) {
            $license->update([
                'expires_at' => $subscription->ends_at,
            ]);
            $extendedCount++;
        }

        $context['licenses_extended'] = $extendedCount;

        return [
            'extended_count' => $extendedCount,
        ];
    }

    public function compensate(WorkflowInstance $instance, array &$context, array $input, array $output): void
    {
        // License 有效期无法真正回退（可能已被客户端读取），记录审计日志即可
        $this->log('warning', 'License 有效期无法回退，需人工干预', [
            'instance_id' => $instance->id,
        ]);
    }

    public function maxRetries(): int
    {
        return 2;
    }

    public function retryDelay(): array|int
    {
        return [10, 30];
    }
}
