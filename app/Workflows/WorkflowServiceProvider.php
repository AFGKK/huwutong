<?php

namespace App\Workflows;

use App\Workflows\Steps\ApprovePayout;
use App\Workflows\Steps\CreateRenewalInvoice;
use App\Workflows\Steps\DisableFeatureFlags;
use App\Workflows\Steps\EnterGracePeriod;
use App\Workflows\Steps\ExpireLicense;
use App\Workflows\Steps\ExtendLicenses;
use App\Workflows\Steps\ExtendSubscription;
use App\Workflows\Steps\FreezeCommission;
use App\Workflows\Steps\NotifyLicenseExpiry;
use App\Workflows\Steps\ProcessRenewalPayment;
use App\Workflows\Steps\ReleaseCommission;
use App\Workflows\Steps\RestoreLicense;
use App\Workflows\Steps\SendExpiryWebhook;
use Illuminate\Support\ServiceProvider;

/**
 * 工作流步骤注册服务提供者
 *
 * 注册所有工作流步骤到 WorkflowEngine，使其可被发现和执行。
 * 后续迁移到 Temporal 时，此处改为注册 Temporal 活动即可。
 */
class WorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerRenewalWorkflow();
        $this->registerLicenseExpiryWorkflow();
        $this->registerCommissionSettlementWorkflow();
        $this->registerLicenseLifecycleWorkflow();
    }

    protected function registerRenewalWorkflow(): void
    {
        WorkflowEngine::registerWorkflow('renewal_pipeline', [
            app(CreateRenewalInvoice::class),
            app(ProcessRenewalPayment::class),
            app(ExtendSubscription::class),
            app(ExtendLicenses::class),
        ]);
    }

    protected function registerLicenseExpiryWorkflow(): void
    {
        WorkflowEngine::registerWorkflow('license_expiry', [
            app(ExpireLicense::class),
            app(DisableFeatureFlags::class),
            app(SendExpiryWebhook::class),
        ]);
    }

    /**
     * 佣金结算工作流: T+30 冻结 → 期满解冻 → 提现审批
     */
    protected function registerCommissionSettlementWorkflow(): void
    {
        WorkflowEngine::registerWorkflow('commission_settlement', [
            app(FreezeCommission::class),
            app(ReleaseCommission::class),
            app(ApprovePayout::class),
        ]);
    }

    /**
     * License 完整生命周期工作流: 过期通知 → 宽限期 → 过期 → 停用 → 恢复
     */
    protected function registerLicenseLifecycleWorkflow(): void
    {
        WorkflowEngine::registerWorkflow('license_lifecycle', [
            app(NotifyLicenseExpiry::class),
            app(EnterGracePeriod::class),
            app(ExpireLicense::class),
            app(DisableFeatureFlags::class),
            app(SendExpiryWebhook::class),
            app(RestoreLicense::class),
        ]);
    }
}
