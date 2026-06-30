<?php

namespace App\Workflows\Steps;

use App\Models\License;
use App\Models\WorkflowInstance;
use App\Services\LicenseService;
use App\Workflows\BaseWorkflowStep;

/**
 * License 生命周期步骤: 恢复 License（从 suspended/frozen → active）
 */
class RestoreLicense extends BaseWorkflowStep
{
    public function __construct(protected LicenseService $licenseService) {}

    public function name(): string
    {
        return 'restore_license';
    }

    public function description(): string
    {
        return '恢复 License 为活跃状态';
    }

    public function execute(WorkflowInstance $instance, array &$context, array $input = []): array
    {
        $licenseId = $context['license_id'] ?? $input['license_id'] ?? null;
        if (! $licenseId) {
            $license = $instance->workflowable;
            if (! $license || ! $license instanceof License) {
                throw new \RuntimeException('缺少 license_id');
            }
            $licenseId = $license->id;
        }

        $license = License::findOrFail($licenseId);

        if (! in_array($license->status, ['suspended', 'frozen'])) {
            $this->log('warning', 'License 状态不是 suspended/frozen，无法恢复', [
                'license_id' => $license->id,
                'status' => $license->status,
            ]);
            return ['license_id' => $license->id, 'skipped' => true];
        }

        $this->licenseService->restore($license, '工作流自动恢复');

        $context['restored_at'] = now()->toIso8601String();
        $context['restored'] = true;

        $this->log('info', 'License 已恢复', ['license_id' => $license->id]);

        return [
            'license_id' => $license->id,
            'restored_at' => $context['restored_at'],
        ];
    }

    public function compensate(WorkflowInstance $instance, array &$context, array $input, array $output): void
    {
        // 恢复操作不可简单回滚，记录审计
        $this->log('warning', 'License 恢复无法回滚', [
            'instance_id' => $instance->id,
            'license_id' => $output['license_id'] ?? null,
        ]);
    }
}
