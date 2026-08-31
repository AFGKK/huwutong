<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\MeteredPrice;
use App\Models\Subscription;
use App\Services\MeteredBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeteredBillingController extends Controller
{
    public function __construct(
        protected MeteredBillingService $meteredBillingService,
    ) {}

    /**
     * 用量计费概览
     */
    public function overview(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return ApiResponse::success(
            $this->meteredBillingService->getOverview($tenantId)
        );
    }

    /**
     * 价格配置列表
     */
    public function prices(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $prices = MeteredPrice::where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('metric_key')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'metric_key' => $p->metric_key,
                'name' => $p->name,
                'unit' => $p->unit,
                'billing_period' => $p->billing_period,
                'tiers' => $p->tiers,
                'base_fee' => $p->base_fee,
                'included_quantity' => $p->included_quantity,
                'max_quantity' => $p->max_quantity,
                'is_active' => $p->is_active,
                'sort_order' => $p->sort_order,
                'created_at' => $p->created_at,
            ]);

        return ApiResponse::success($prices);
    }

    /**
     * 创建/更新价格配置
     */
    public function upsertPrice(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'metric_key' => 'required|string|max:100',
            'name' => 'required|string|max:200',
            'unit' => 'required|string|max:50',
            'billing_period' => 'required|in:monthly,quarterly,yearly',
            'tiers' => 'required|array',
            'tiers.*.from' => 'required|numeric|min:0',
            'tiers.*.to' => 'nullable|numeric|gt:tiers.*.from',
            'tiers.*.unit_price' => 'required|numeric|min:0',
            'base_fee' => 'nullable|numeric|min:0',
            'included_quantity' => 'nullable|numeric|min:0',
            'max_quantity' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $price = MeteredPrice::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'metric_key' => $validated['metric_key'],
                'billing_period' => $validated['billing_period'],
            ],
            [
                'name' => $validated['name'],
                'unit' => $validated['unit'],
                'tiers' => $validated['tiers'],
                'base_fee' => $validated['base_fee'] ?? 0,
                'included_quantity' => $validated['included_quantity'] ?? 0,
                'max_quantity' => $validated['max_quantity'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]
        );

        return ApiResponse::success($price, __('app.metered_billing.price_saved'));
    }

    /**
     * 删除价格配置
     */
    public function deletePrice(int $id): JsonResponse
    {
        $price = MeteredPrice::findOrFail($id);
        $price->delete();

        return ApiResponse::success(null, __('app.metered_billing.price_deleted'));
    }

    /**
     * 获取可用计量指标列表
     */
    public function availableMetrics(): JsonResponse
    {
        $metrics = \App\Services\UsageMeterService::METRICS;

        $result = [];
        foreach ($metrics as $key => $config) {
            $result[] = [
                'metric_key' => $key,
                'name' => $config['name'],
                'unit' => $config['unit'],
                'window' => $config['window'],
            ];
        }

        return ApiResponse::success($result);
    }

    /**
     * 为订阅生成用量账单
     */
    public function generateInvoice(Request $request, Subscription $subscription): JsonResponse
    {
        $dryRun = $request->boolean('dry_run', false);

        $result = $this->meteredBillingService->generateMeteredInvoice(
            $subscription, null, null, $dryRun
        );

        if (!empty($result['errors'])) {
            return ApiResponse::error(implode('; ', $result['errors']), 400);
        }

        return ApiResponse::success($result, $dryRun ? __('app.metered_billing.estimation_done') : __('app.metered_billing.bill_generated'));
    }

    /**
     * 批量生成用量账单
     */
    public function batchGenerateInvoices(Request $request): JsonResponse
    {
        $dryRun = $request->boolean('dry_run', false);

        $result = $this->meteredBillingService->batchGenerateMeteredInvoices('monthly', $dryRun);

        return ApiResponse::success($result, __('app.metered_billing.batch_result', ['total' => $result['total'], 'success' => $result['success']]));
    }

    /**
     * 查看某个License的用量及费用
     */
    public function licenseUsage(Request $request, License $license): JsonResponse
    {
        $validated = $request->validate([
            'metric_key' => 'nullable|string|max:100',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
        ]);

        $result = $this->meteredBillingService->getLicenseUsage(
            $license,
            $validated['metric_key'] ?? null,
            $validated['period_start'] ?? null,
            $validated['period_end'] ?? null,
        );

        return ApiResponse::success($result);
    }

    /**
     * 更新订阅的用量计费配置
     */
    public function updateSubscriptionConfig(Request $request, Subscription $subscription): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'billing_period' => 'nullable|in:monthly,quarterly,yearly',
            'cap_type' => 'nullable|in:soft,hard',
            'monthly_cap' => 'nullable|numeric|min:0',
        ]);

        $meteredConfig = $subscription->metered_config ?? [];
        $meteredConfig['enabled'] = $validated['enabled'];

        if (isset($validated['billing_period'])) {
            $meteredConfig['billing_period'] = $validated['billing_period'];
        }
        if (isset($validated['cap_type'])) {
            $meteredConfig['cap_type'] = $validated['cap_type'];
        }
        if (isset($validated['monthly_cap'])) {
            $meteredConfig['monthly_cap'] = $validated['monthly_cap'];
        }

        $subscription->update(['metered_config' => $meteredConfig]);

        return ApiResponse::success($subscription->fresh(), __('app.metered_billing.config_updated'));
    }

    /**
     * 获取启用了用量计费的订阅列表
     */
    public function meteredSubscriptions(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $subscriptions = Subscription::where('tenant_id', $tenantId)
            ->whereNotNull('metered_config')
            ->where('metered_config->enabled', true)
            ->with('customer:id,name', 'product:id,name')
            ->latest()
            ->paginate($perPage = (int) $request->get('per_page', 20));

        return ApiResponse::paginated($subscriptions);
    }
}
