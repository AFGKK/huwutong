<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BundlePlan;
use App\Models\PlanUpgradePath;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function __construct(
        protected PlanService $service,
    ) {}

    // ═══════════ 套餐查询 ═══════════

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->listPlans(
            $request->only(['is_public', 'is_active', 'product_id', 'search', 'per_page'])
        ));
    }

    public function show(PricingPlan $plan): JsonResponse
    {
        return ApiResponse::success($this->service->getPlanWithBundles($plan));
    }

    // ═══════════ 捆绑规则管理 ═══════════

    public function bundleRules(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->listBundleRules());
    }

    public function storeBundleRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_plan_id' => 'required|integer|exists:pricing_plans,id',
            'included_plan_id' => 'required|integer|exists:pricing_plans,id|different:parent_plan_id',
            'type' => 'required|in:optional,required,upgrade',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'fixed_discount' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        return ApiResponse::success($this->service->createBundleRule($validated), '捆绑规则已创建', 201);
    }

    public function updateBundleRule(Request $request, BundlePlan $bundle): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|in:optional,required,upgrade',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'fixed_discount' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        return ApiResponse::success($this->service->updateBundleRule($bundle, $validated), '已更新');
    }

    public function destroyBundleRule(BundlePlan $bundle): JsonResponse
    {
        $this->service->deleteBundleRule($bundle);
        return ApiResponse::success(null, '已删除');
    }

    // ═══════════ 升级路径管理 ═══════════

    public function upgradePaths(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->listUpgradePaths());
    }

    public function storeUpgradePath(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_plan_id' => 'required|integer|exists:pricing_plans,id',
            'to_plan_id' => 'required|integer|exists:pricing_plans,id|different:from_plan_id',
            'proration_ratio' => 'required|numeric|min:0|max:1',
            'additional_fee' => 'nullable|numeric|min:0',
            'allow_downgrade' => 'boolean',
            'is_active' => 'boolean',
        ]);

        return ApiResponse::success($this->service->createUpgradePath($validated), '升级路径已创建', 201);
    }

    public function updateUpgradePath(Request $request, PlanUpgradePath $path): JsonResponse
    {
        $validated = $request->validate([
            'proration_ratio' => 'nullable|numeric|min:0|max:1',
            'additional_fee' => 'nullable|numeric|min:0',
            'allow_downgrade' => 'boolean',
            'is_active' => 'boolean',
        ]);

        return ApiResponse::success($this->service->updateUpgradePath($path, $validated), '已更新');
    }

    public function destroyUpgradePath(PlanUpgradePath $path): JsonResponse
    {
        $this->service->deleteUpgradePath($path);
        return ApiResponse::success(null, '已删除');
    }

    // ═══════════ 升级计算与执行 ═══════════

    public function calculateUpgrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_plan_id' => 'required|integer|exists:pricing_plans,id',
            'to_plan_id' => 'required|integer|exists:pricing_plans,id',
            'billing_period' => 'nullable|in:monthly,quarterly,semi_annually,yearly',
        ]);

        $from = PricingPlan::findOrFail($validated['from_plan_id']);
        $to = PricingPlan::findOrFail($validated['to_plan_id']);
        $period = $validated['billing_period'] ?? 'monthly';

        try {
            return ApiResponse::success($this->service->calculateUpgrade($from, $to, $period));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('UPGRADE_NOT_ALLOWED', $e->getMessage(), 422);
        }
    }

    public function executeUpgrade(Request $request, Subscription $subscription): JsonResponse
    {
        $validated = $request->validate([
            'to_plan_id' => 'required|integer|exists:pricing_plans,id',
            'billing_period' => 'nullable|in:monthly,quarterly,semi_annually,yearly',
            'notes' => 'nullable|string|max:500',
            'force' => 'boolean',
        ]);

        $toPlan = PricingPlan::findOrFail($validated['to_plan_id']);

        try {
            $log = $this->service->executeUpgrade($subscription, $toPlan, $validated);
            return ApiResponse::success($log, '套餐变更成功');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('UPGRADE_FAILED', $e->getMessage(), 422);
        }
    }

    // ═══════════ 升级日志 ═══════════

    public function upgradeLogs(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->listUpgradeLogs(
            $request->only(['subscription_id', 'type', 'status'])
        ));
    }

    // ═══════════ 门户端 ═══════════

    public function publicPlans(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getPublicPlans(
            $request->integer('product_id')
        ));
    }

    public function upgradeOptions(Subscription $subscription): JsonResponse
    {
        if ($subscription->customer_id !== auth()->user()?->customer?->id) {
            return ApiResponse::error('FORBIDDEN', '无权操作', 403);
        }

        return ApiResponse::success($this->service->getSubscriptionUpgradeOptions($subscription));
    }
}
