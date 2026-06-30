<?php

namespace App\Workflows\Steps;

use App\Models\License;
use App\Models\WorkflowInstance;
use App\Workflows\BaseWorkflowStep;

/**
 * 步骤2: 禁用关联功能标志
 */
class DisableFeatureFlags extends BaseWorkflowStep
{
    public function name(): string
    {
        return 'disable_feature_flags';
    }

    public function description(): string
    {
        return '禁用过期 License 关联的功能标志';
    }

    public function execute(WorkflowInstance $instance, array &$context, array $input = []): array
    {
        /** @var License $license */
        $license = $instance->workflowable;
        $tenantId = $license?->tenant_id ?? $context['tenant_id'] ?? null;

        if (!$tenantId) {
            $this->log('warning', '缺少 tenant_id，跳过功能标志禁用');
            return ['skipped' => true];
        }

        // 禁用该租户的所有付费功能标志
        $affected = \App\Models\FeatureFlag::where('tenant_id', $tenantId)
            ->where('is_premium', true)
            ->update(['is_active' => false]);

        $context['feature_flags_disabled'] = $affected;

        $this->log('info', '功能标志已禁用', ['tenant_id' => $tenantId, 'affected' => $affected]);

        return [
            'tenant_id' => $tenantId,
            'affected_count' => $affected,
        ];
    }

    public function compensate(WorkflowInstance $instance, array &$context, array $input, array $output): void
    {
        // 恢复标志状态需要知道原来的值，这里记录需要人工检查
        $this->log('warning', '功能标志需要手动恢复', [
            'tenant_id' => $output['tenant_id'] ?? null,
        ]);
    }
}
