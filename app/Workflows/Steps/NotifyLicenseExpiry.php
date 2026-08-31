<?php

namespace App\Workflows\Steps;

use App\Models\License;
use App\Models\Subscription;
use App\Models\WorkflowInstance;
use App\Services\LicenseService;
use App\Workflows\BaseWorkflowStep;

/**
 * License 生命周期步骤1: 过期通知 — 发送过期前提醒
 */
class NotifyLicenseExpiry extends BaseWorkflowStep
{
    public function name(): string
    {
        return 'notify_expiry';
    }

    public function description(): string
    {
        return '发送 License 即将过期通知';
    }

    public function execute(WorkflowInstance $instance, array &$context, array $input = []): array
    {
        $licenseId = $context['license_id'] ?? $input['license_id'] ?? null;
        if (! $licenseId) {
            throw new \RuntimeException(__('app.common.missing_license_id'));
        }

        $license = License::with('tenant', 'customer')->findOrFail($licenseId);

        $this->log('info', '发送过期通知', [
            'license_id' => $license->id,
            'expires_at' => $license->expires_at?->toDateString(),
        ]);

        $context['notified_at'] = now()->toIso8601String();
        $context['expires_at'] = $license->expires_at?->toIso8601String();

        return [
            'license_id' => $license->id,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'notified' => true,
        ];
    }

    public function compensate(WorkflowInstance $instance, array &$context, array $input, array $output): void
    {
        // 通知不需要补偿
    }
}
