<?php

namespace App\Workflows\Steps;

use App\Models\License;
use App\Models\WorkflowInstance;
use App\Workflows\BaseWorkflowStep;

/**
 * 步骤3: 发送过期 Webhook
 */
class SendExpiryWebhook extends BaseWorkflowStep
{
    public function name(): string
    {
        return 'send_expiry_webhook';
    }

    public function description(): string
    {
        return '发送 License 过期 Webhook 通知';
    }

    public function execute(WorkflowInstance $instance, array &$context, array $input = []): array
    {
        /** @var License $license */
        $license = $instance->workflowable;
        if (!$license) {
            throw new \RuntimeException('工作流未关联 License');
        }

        try {
            $webhookService = app(\App\Services\WebhookService::class);
            $webhookService->dispatch(
                'license.expired',
                [
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                    'product_id' => $license->product_id,
                    'expired_at' => now()->toIso8601String(),
                    'tenant_id' => $license->tenant_id,
                ]
            );
            $context['webhook_dispatched'] = true;

            $this->log('info', '过期 Webhook 已发送', ['license_id' => $license->id]);
        } catch (\Throwable $e) {
            $this->log('warning', 'Webhook 发送失败，继续流程', [
                'error' => $e->getMessage(),
            ]);
            $context['webhook_error'] = $e->getMessage();
        }

        return [
            'dispatched' => $context['webhook_dispatched'] ?? false,
            'error' => $context['webhook_error'] ?? null,
        ];
    }

    public function compensate(WorkflowInstance $instance, array &$context, array $input, array $output): void
    {
        // Webhook 无法撤回
        $this->log('info', 'Webhook 无需补偿');
    }
}
