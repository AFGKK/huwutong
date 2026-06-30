<?php

namespace App\Workflows\Steps;

use App\Models\Subscription;
use App\Models\WorkflowInstance;
use App\Workflows\BaseWorkflowStep;

/**
 * 步骤3: 延长订阅周期
 */
class ExtendSubscription extends BaseWorkflowStep
{
    public function name(): string
    {
        return 'extend_subscription';
    }

    public function description(): string
    {
        return '延长订阅有效期';
    }

    public function execute(WorkflowInstance $instance, array &$context, array $input = []): array
    {
        /** @var Subscription $subscription */
        $subscription = $instance->workflowable;
        if (!$subscription) {
            throw new \RuntimeException('工作流未关联 Subscription');
        }

        $billingPeriod = $subscription->billing_period ?? 'monthly';
        $extensionDays = match ($billingPeriod) {
            'yearly' => 365,
            'quarterly' => 90,
            'monthly' => 30,
            'weekly' => 7,
            default => 30,
        };

        $oldEndsAt = $subscription->ends_at;

        $subscription->update([
            'status' => 'active',
            'last_billing_at' => now(),
            'next_billing_at' => now()->addDays($extensionDays),
            'ends_at' => $subscription->ends_at
                ? $subscription->ends_at->addDays($extensionDays)
                : now()->addDays($extensionDays),
            'grace_period_start' => null,
        ]);

        $context['subscription_extended'] = true;
        $context['old_ends_at'] = $oldEndsAt?->toIso8601String();
        $context['new_ends_at'] = $subscription->ends_at->toIso8601String();

        return [
            'old_ends_at' => $context['old_ends_at'],
            'new_ends_at' => $context['new_ends_at'],
            'extended_days' => $extensionDays,
        ];
    }

    public function compensate(WorkflowInstance $instance, array &$context, array $input, array $output): void
    {
        /** @var Subscription $subscription */
        $subscription = $instance->workflowable;
        if ($subscription) {
            $subscription->update([
                'ends_at' => $output['old_ends_at'] ?? $subscription->ends_at,
                'next_billing_at' => $context['previous_next_billing'] ?? $subscription->next_billing_at,
            ]);
        }
    }

    public function maxRetries(): int
    {
        return 1;
    }

    public function retryDelay(): array|int
    {
        return [30];
    }
}
