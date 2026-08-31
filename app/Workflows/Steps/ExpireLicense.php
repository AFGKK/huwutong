<?php

namespace App\Workflows\Steps;

use App\Models\License;
use App\Models\WorkflowInstance;
use App\Services\LicenseService;
use App\Workflows\BaseWorkflowStep;
use Illuminate\Support\Facades\Log;

/**
 * 步骤1: 过期 License
 */
class ExpireLicense extends BaseWorkflowStep
{
    public function __construct(protected LicenseService $licenseService) {}

    public function name(): string
    {
        return 'expire_license';
    }

    public function description(): string
    {
        return '标记 License 为过期状态';
    }

    public function execute(WorkflowInstance $instance, array &$context, array $input = []): array
    {
        /** @var License $license */
        $license = $instance->workflowable;
        if (!$license || !$license instanceof License) {
            // 可能是一个 bulk 操作，从 context 中读取 license_id
            $licenseId = $context['license_id'] ?? $input['license_id'] ?? null;
            if (!$licenseId) {
                throw new \RuntimeException(__('app.common.missing_license_id'));
            }
            $license = License::findOrFail($licenseId);
        }

        $this->log('info', '过期 License', ['license_id' => $license->id]);

        $beforeStatus = $license->status;
        $license->update([
            'status' => 'expired',
            'expires_at' => now(),
        ]);

        // 尝试执行过期服务逻辑
        try {
            $this->licenseService->expire($license);
        } catch (\Throwable $e) {
            Log::warning('LicenseExpiry: licenseService->expire() 失败，但 License 已标记', [
                'error' => $e->getMessage(),
            ]);
        }

        $context['license_before_status'] = $beforeStatus;
        $context['license_expired'] = true;

        return [
            'license_id' => $license->id,
            'before_status' => $beforeStatus,
        ];
    }

    public function compensate(WorkflowInstance $instance, array &$context, array $input, array $output): void
    {
        if (isset($output['license_id']) && $context['license_before_status'] ?? false) {
            $license = License::find($output['license_id']);
            if ($license) {
                $license->update(['status' => $context['license_before_status']]);
                $this->log('info', 'License 状态已恢复(补偿)', [
                    'license_id' => $license->id,
                    'restored_status' => $context['license_before_status'],
                ]);
            }
        }
    }

    public function maxRetries(): int
    {
        return 2;
    }

    public function retryDelay(): array|int
    {
        return [10, 60];
    }
}
