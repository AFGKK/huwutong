<?php

namespace App\Services;

use App\Models\BundlePlan;
use App\Models\PlanUpgradeLog;
use App\Models\PlanUpgradePath;
use App\Models\PricingPlan;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlanService
{
    // ═══════════ 套餐查询 ═══════════

    public function listPlans(array $filters = [])
    {
        $query = PricingPlan::with('product')
            ->orderBy('sort_order')
            ->orderBy('price_monthly');

        if (!empty($filters['is_public'])) $query->where('is_public', true);
        if (!empty($filters['is_active'])) $query->where('is_active', true);
        if (!empty($filters['product_id'])) $query->where('product_id', $filters['product_id']);
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('slug', 'like', "%{$filters['search']}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function getPlanWithBundles(PricingPlan $plan): array
    {
        $plan->load('product');

        $bundles = BundlePlan::with('includedPlan.product')
            ->where('parent_plan_id', $plan->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($bundle) use ($plan) {
                $included = $bundle->includedPlan;
                $discounted = $this->calculateBundlePrice($plan, $included, $bundle);
                return [
                    'bundle' => $bundle,
                    'plan' => $included->toSummary(),
                    'discounted_price' => $discounted,
                    'saving' => round($included->getPrice('monthly') - $discounted, 2),
                ];
            });

        return [
            'plan' => $plan->toSummary(),
            'bundles' => $bundles,
        ];
    }

    public function calculateBundlePrice(PricingPlan $parent, PricingPlan $included, BundlePlan $bundle): float
    {
        $basePrice = $included->getPrice('monthly');
        $price = $basePrice;

        if ($bundle->discount_percent > 0) {
            $price = $basePrice * (1 - $bundle->discount_percent / 100);
        }
        if ($bundle->fixed_discount !== null && $bundle->fixed_discount > 0) {
            $price = max(0, $basePrice - $bundle->fixed_discount);
        }

        return round($price, 2);
    }

    // ═══════════ 升降级计算 ═══════════

    /**
     * 计算升降级费用
     *
     * @return array{type:string, credit:float, charge:float, discount:float, details:array}
     */
    public function calculateUpgrade(PricingPlan $fromPlan, PricingPlan $toPlan, string $billingPeriod = 'monthly'): array
    {
        $fromPrice = $fromPlan->getPrice($billingPeriod);
        $toPrice = $toPlan->getPrice($billingPeriod);

        $type = $toPrice > $fromPrice ? 'upgrade' : ($toPrice < $fromPrice ? 'downgrade' : 'crossgrade');

        // 查找路径规则
        $path = PlanUpgradePath::where('from_plan_id', $fromPlan->id)
            ->where('to_plan_id', $toPlan->id)
            ->where('is_active', true)
            ->first();

        // 降级需要路径规则明确允许
        if ($type === 'downgrade' && (!$path || !$path->allow_downgrade)) {
            throw new RuntimeException('不允许降级到该套餐');
        }

        $prorationRatio = $path?->proration_ratio ?? 0.5; // 默认50%折算
        $additionalFee = $path?->additional_fee ?? 0;

        // 假设当前周期已经过了一半时间（真实场景需要计算实际时间比例）
        // 剩余价值 = 已付价格 * (1 - 已使用比例 * prorationRatio)
        $remainingRatio = 1 - $prorationRatio; // 保留比例
        $credit = $fromPrice * $remainingRatio;
        $charge = max(0, $toPrice - $credit) + $additionalFee;
        $discount = 0;

        return [
            'type' => $type,
            'from_price' => $fromPrice,
            'to_price' => $toPrice,
            'credit' => round($credit, 2),
            'charge' => round($charge, 2),
            'discount' => round($discount, 2),
        ];
    }

    /**
     * 执行订阅升降级
     */
    public function executeUpgrade(Subscription $subscription, PricingPlan $toPlan, array $options = []): PlanUpgradeLog
    {
        $fromPlan = PricingPlan::where('slug', $subscription->pricing_plan_slug)->firstOrFail();
        $billingPeriod = $options['billing_period'] ?? $subscription->billing_period;

        $calculation = $this->calculateUpgrade($fromPlan, $toPlan, $billingPeriod);

        if ($calculation['type'] === 'downgrade' && ($options['force'] ?? false) === false) {
            throw new RuntimeException('降级需在续费周期生效，请使用 force=schedule');
        }

        return DB::transaction(function () use ($subscription, $toPlan, $fromPlan, $calculation, $billingPeriod, $options) {
            $log = PlanUpgradeLog::create([
                'subscription_id' => $subscription->id,
                'from_plan_id' => $fromPlan->id,
                'to_plan_id' => $toPlan->id,
                'type' => $calculation['type'],
                'original_price' => $calculation['from_price'],
                'new_price' => $calculation['to_price'],
                'credit' => $calculation['credit'],
                'charge' => $calculation['charge'],
                'discount' => $calculation['discount'],
                'status' => 'completed',
                'details' => [
                    'billing_period' => $billingPeriod,
                    'options' => $options,
                ],
                'operator_id' => auth()->id(),
                'notes' => $options['notes'] ?? null,
                'completed_at' => now(),
            ]);

            // 更新订阅
            $subscription->update([
                'pricing_plan_slug' => $toPlan->slug,
                'plan' => $toPlan->name,
                'price' => $toPlan->getPrice($billingPeriod),
                'billing_period' => $billingPeriod,
            ]);

            return $log;
        });
    }

    // ═══════════ 捆绑管理 ═══════════

    public function listBundleRules(array $filters = [])
    {
        $query = BundlePlan::with(['parentPlan:id,name,slug', 'includedPlan:id,name,slug'])
            ->orderBy('created_at', 'desc');

        return $query->paginate(20);
    }

    public function createBundleRule(array $data): BundlePlan
    {
        return BundlePlan::create($data);
    }

    public function updateBundleRule(BundlePlan $bundle, array $data): BundlePlan
    {
        $bundle->update($data);
        return $bundle;
    }

    public function deleteBundleRule(BundlePlan $bundle): void
    {
        $bundle->delete();
    }

    // ═══════════ 升级路径管理 ═══════════

    public function listUpgradePaths(array $filters = [])
    {
        $query = PlanUpgradePath::with(['fromPlan:id,name,slug', 'toPlan:id,name,slug'])
            ->orderBy('from_plan_id')
            ->orderBy('to_plan_id');

        return $query->paginate(20);
    }

    public function createUpgradePath(array $data): PlanUpgradePath
    {
        return PlanUpgradePath::create($data);
    }

    public function updateUpgradePath(PlanUpgradePath $path, array $data): PlanUpgradePath
    {
        $path->update($data);
        return $path;
    }

    public function deleteUpgradePath(PlanUpgradePath $path): void
    {
        $path->delete();
    }

    // ═══════════ 升级日志 ═══════════

    public function listUpgradeLogs(array $filters = [], int $perPage = 20)
    {
        $query = PlanUpgradeLog::with(['subscription', 'fromPlan:id,name,slug', 'toPlan:id,name,slug', 'operator:id,name'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['subscription_id'])) $query->where('subscription_id', $filters['subscription_id']);
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);

        return $query->paginate($perPage);
    }

    // ═══════════ 门户端 ═══════════

    public function getPublicPlans(int $productId = null)
    {
        $query = PricingPlan::with('product')
            ->where('is_public', true)
            ->where('is_active', true)
            ->ordered();

        if ($productId) {
            $query->where('product_id', $productId);
        }

        return $query->get()->map(function ($plan) {
            $data = $plan->toSummary();
            $data['bundles'] = BundlePlan::with('includedPlan')
                ->where('parent_plan_id', $plan->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn($b) => [
                    'id' => $b->id,
                    'plan' => $b->includedPlan->toSummary(),
                    'discount_percent' => $b->discount_percent,
                    'fixed_discount' => $b->fixed_discount,
                ]);
            return $data;
        });
    }

    public function getSubscriptionUpgradeOptions(Subscription $subscription)
    {
        $currentPlan = PricingPlan::where('slug', $subscription->pricing_plan_slug)->first();
        if (!$currentPlan) {
            return [];
        }

        // 查找所有可升级到的方案
        $paths = PlanUpgradePath::with('toPlan')
            ->where('from_plan_id', $currentPlan->id)
            ->where('is_active', true)
            ->get();

        return $paths->map(function ($path) use ($currentPlan, $subscription) {
            $calc = $this->calculateUpgrade(
                $currentPlan,
                $path->toPlan,
                $subscription->billing_period
            );
            return [
                'path' => $path,
                'to_plan' => $path->toPlan->toSummary(),
                'calculation' => $calc,
            ];
        });
    }
}
