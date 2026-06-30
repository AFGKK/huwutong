<?php

namespace App\Workflows\Steps;

use App\Models\License;
use App\Models\Subscription;
use App\Models\WorkflowInstance;
use App\Services\LicenseService;
use App\Workflows\BaseWorkflowStep;

/**
 * License 生命周期步骤2: 进入宽限期 — 将订阅转为 grace 状态
 */
class EnterGracePeriod extends BaseWorkflowStep
{
    public function __construct(protected LicenseService $licenseService) {}

    public function name(): string
    {
        return 'enter_grace';
    }

    public function description(): string
    {
        return '进入宽限期（订阅续费失败后）';
    }

    public function execute(WorkflowInstance $instance, array &$context, array $input = []): array
    {
        $subscriptionId = $context['subscription_id'] ?? $input['subscription_id'] ?? null;

        if ($subscriptionId) {
            $subscription = Subscription::findOrFail($subscriptionId);
            $subscription->enterGracePeriod();

            $context['grace_started_at'] = now()->toIso8601String();
            $context['grace_ends_at'] = $subscription->grace_ends_at?->toIso8601String();

            $this->log('info', '订阅进入宽限期', [
                'subscription_id' => $subscription->id,
                'grace_ends_at' => $subscription->grace_ends_at?->toDateString(),
            ]);

            return [
                'subscription_id' => $subscription->id,
                'grace_ends_at' => $subscription->grace_ends_at?->toIso8601String(),
            ];
        }

        // 没有 subscription 的 License，直接标记过期
        $licenseId = $context['license_id'] ?? $input['license_id'] ?? null;
        if (! $licenseId) {
            throw new \RuntimeException('缺少 license_id 或 subscription_id');
        }

        $this->log('info', '无订阅关联，跳过宽限期', ['license_id' => $licenseId]);

        return ['skipped' => true];
    }

    public function compensate(WorkflowInstance $instance, array &$context, array $input, array $output): void
    {
        if (isset($output['subscription_id'])) {
            $subscription = Subscription::find($output['subscription_id']);
            if ($subscription && $subscription->status === 'grace') {
                $subscription->update(['status' => 'active', 'grace_ends_at' => null]);
                $this->log('info', '宽限期已撤销(补偿)', ['subscription_id' => $subscription->id]);
            }
        }
    }

    public function maxRetries(): int
    {
        return 2;
    }

    public function retryDelay(): array|int
    {
        return [30, 60];
    }
}
