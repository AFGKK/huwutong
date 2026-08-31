<?php

namespace App\Services;

use App\Models\AutoRenewalPlan;
use App\Models\AutoRenewalSubscription;
use App\Models\AutoRenewalAttempt;
use App\Models\BillingCycle;
use App\Models\License;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * M3-85 订阅商品自动续费+自助升级/降级
 */
class AutoRenewalService
{
    /**
     * 创建订阅
     */
    public function subscribe(int $customerId, int $planId, int $licenseId): AutoRenewalSubscription
    {
        $plan = AutoRenewalPlan::findOrFail($planId);
        $months = $this->getPeriodMonths($plan->billing_period);

        return AutoRenewalSubscription::create([
            'tenant_id' => $plan->tenant_id,
            'customer_id' => $customerId,
            'auto_renewal_plan_id' => $plan->id,
            'license_id' => $licenseId,
            'status' => 'active',
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonths($months),
            'next_renewal_at' => now()->addMonths($months),
            'failed_attempts' => 0,
        ]);
    }

    /**
     * 执行续费
     */
    public function renew(AutoRenewalSubscription $subscription): array
    {
        $plan = $subscription->plan;
        $months = $this->getPeriodMonths($plan->billing_period);

        try {
            // 模拟支付扣款 - 实际应调用支付网关
            $success = true;

            $attempt = AutoRenewalAttempt::create([
                'auto_renewal_subscription_id' => $subscription->id,
                'attempt_type' => 'renewal',
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'status' => $success ? 'success' : 'failed',
                'result_data' => ['method' => 'auto_renewal'],
            ]);

            if ($success) {
                $subscription->update([
                    'current_period_starts_at' => $subscription->current_period_ends_at,
                    'current_period_ends_at' => Carbon::parse($subscription->current_period_ends_at)->addMonths($months),
                    'next_renewal_at' => Carbon::parse($subscription->current_period_ends_at)->addMonths($months),
                    'last_renewal_at' => now(),
                    'failed_attempts' => 0,
                ]);

                // 延长 License 有效期
                License::where('id', $subscription->license_id)
                    ->update(['expires_at' => $subscription->current_period_ends_at]);

                return ['success' => true, 'message' => __('app.common.renewal_success'), 'attempt_id' => $attempt->id];
            }

            throw new \RuntimeException(__("app.auto_renewal.payment_failed"));
        } catch (\Exception $e) {
            $subscription->increment('failed_attempts');
            $subscription->update(['next_renewal_at' => now()->addHours(24)]);

            if ($subscription->failed_attempts >= $plan->max_retries) {
                $subscription->update(['status' => 'failed']);
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 升级订阅
     */
    public function upgrade(AutoRenewalSubscription $subscription, int $targetPlanId): array
    {
        $currentPlan = $subscription->plan;
        $targetPlan = AutoRenewalPlan::findOrFail($targetPlanId);

        // 检查升级路径
        $allowedUpgrades = $currentPlan->upgrade_paths ?? [];
        if (!in_array($targetPlanId, $allowedUpgrades)) {
            return ['success' => false, 'message' => __('app.common.upgrade_to_plan_not_allowed')];
        }

        $priceDiff = $targetPlan->price - $currentPlan->price;
        if ($priceDiff <= 0) {
            return ['success' => false, 'message' => __('app.common.upgrade_plan_price_higher')];
        }

        // 按比例计算剩余价值折算
        $remainingDays = now()->diffInDays($subscription->current_period_ends_at, false);
        $totalDays = $subscription->current_period_starts_at->diffInDays($subscription->current_period_ends_at);
        $remainingValue = $totalDays > 0 ? ($currentPlan->price * $remainingDays / $totalDays) : 0;
        $upgradePrice = max(0, $priceDiff - $remainingValue);

        $subscription->update([
            'auto_renewal_plan_id' => $targetPlanId,
            'metadata' => array_merge($subscription->metadata ?? [], [
                'upgraded_from' => $currentPlan->id,
                'upgrade_price' => $upgradePrice,
            ]),
        ]);

        AutoRenewalAttempt::create([
            'auto_renewal_subscription_id' => $subscription->id,
            'attempt_type' => 'upgrade',
            'amount' => $upgradePrice,
            'currency' => $targetPlan->currency,
            'status' => 'success',
            'result_data' => ['from_plan' => $currentPlan->id, 'to_plan' => $targetPlanId],
        ]);

        return ['success' => true, 'upgrade_price' => $upgradePrice, 'message' => __('app.common.upgrade_success')];
    }

    /**
     * 降级订阅
     */
    public function downgrade(AutoRenewalSubscription $subscription, int $targetPlanId): array
    {
        $currentPlan = $subscription->plan;
        $targetPlan = AutoRenewalPlan::findOrFail($targetPlanId);

        $allowedDowngrades = $currentPlan->downgrade_paths ?? [];
        if (!in_array($targetPlanId, $allowedDowngrades)) {
            return ['success' => false, 'message' => __('app.common.downgrade_to_plan_not_allowed')];
        }

        $subscription->update([
            'auto_renewal_plan_id' => $targetPlanId,
            'metadata' => array_merge($subscription->metadata ?? [], [
                'downgraded_from' => $currentPlan->id,
                'apply_at' => $subscription->current_period_ends_at->toIso8601String(),
            ]),
        ]);

        AutoRenewalAttempt::create([
            'auto_renewal_subscription_id' => $subscription->id,
            'attempt_type' => 'downgrade',
            'amount' => 0,
            'currency' => $targetPlan->currency,
            'status' => 'success',
        ]);

        return ['success' => true, 'message' => __('app.common.downgrade_effective_at_cycle_end')];
    }

    /**
     * 取消订阅
     */
    public function cancel(AutoRenewalSubscription $subscription): void
    {
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * 暂停订阅
     */
    public function pause(AutoRenewalSubscription $subscription): void
    {
        $subscription->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);
    }

    /**
     * 恢复订阅
     */
    public function resume(AutoRenewalSubscription $subscription): void
    {
        $subscription->update([
            'status' => 'active',
            'paused_at' => null,
            'next_renewal_at' => $subscription->current_period_ends_at,
        ]);
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $active = AutoRenewalSubscription::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $paused = AutoRenewalSubscription::where('tenant_id', $tenantId)->where('status', 'paused')->count();
        $cancelled = AutoRenewalSubscription::where('tenant_id', $tenantId)->where('status', 'cancelled')->count();
        $failed = AutoRenewalSubscription::where('tenant_id', $tenantId)->where('status', 'failed')->count();
        $dueRenewal = AutoRenewalSubscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('next_renewal_at', '<=', now()->addDays(7))
            ->count();
        $totalPlans = AutoRenewalPlan::where('tenant_id', $tenantId)->count();

        return compact('active', 'paused', 'cancelled', 'failed', 'dueRenewal', 'totalPlans');
    }

    /**
     * 处理到期续费（批量）
     */
    public function processDueRenewals(int $limit = 50): array
    {
        $due = AutoRenewalSubscription::where('status', 'active')
            ->where('next_renewal_at', '<=', now())
            ->limit($limit)
            ->get();

        $results = [];
        foreach ($due as $sub) {
            $results[] = $this->renew($sub);
        }

        return $results;
    }

    protected function getPeriodMonths(string $period): int
    {
        $cycle = BillingCycle::resolvePeriod($period);
        if (!$cycle) {
            return 1;
        }
        return ($cycle->months ?? 0) + (int) ceil(($cycle->days ?? 0) / 30);
    }
}
