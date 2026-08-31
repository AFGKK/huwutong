<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PromotionRule;
use App\Services\PromotionEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PromotionEngineController extends Controller
{
    public function __construct(
        protected PromotionEngineService $promotionEngineService,
    ) {}

    /**
     * 规则列表
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $query = PromotionRule::where('tenant_id', $tenantId)
            ->with('creator:id,name');

        // 过滤
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('slug', 'like', "%{$request->search}%");
            });
        }

        $rules = $query->orderBy('priority')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return ApiResponse::success($rules);
    }

    /**
     * 创建规则
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'type' => 'required|string|in:amount_off,percent_off,buy_x_get_y,fixed_price',
            'description' => 'nullable|string|max:1000',
            'condition_type' => 'required|string|in:subtotal,quantity,items_count',
            'condition_value' => 'required|numeric|min:0',
            'discount_value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'applicable_products' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'excluded_products' => 'nullable|array',
            'stackable_with_coupon' => 'boolean',
            'stackable_with_other_rules' => 'boolean',
            'priority' => 'integer|min:0|max:999',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'budget' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'tiers' => 'nullable|array',
            'tiers.*.from' => 'required|numeric|min:0',
            'tiers.*.to' => 'nullable|numeric',
            'tiers.*.type' => 'required|string|in:amount_off,percent_off',
            'tiers.*.value' => 'required|numeric|min:0',
            'buy_quantity' => 'nullable|integer|min:1',
            'free_quantity' => 'nullable|integer|min:1',
            'free_products' => 'nullable|array',
            'status' => 'string|in:draft,active,paused',
        ]);

        $tenantId = $request->user()->tenant_id;
        $validated['tenant_id'] = $tenantId;
        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(6);
        $validated['created_by'] = $request->user()->id;

        $rule = PromotionRule::create($validated);

        return ApiResponse::success($rule->load('creator:id,name'), __('app.promotion_engine.rule_created'));
    }

    /**
     * 规则详情
     */
    public function show(Request $request, PromotionRule $promotionRule): JsonResponse
    {
        $this->authorizeTenant($request, $promotionRule);

        return ApiResponse::success($promotionRule->load('creator:id,name'));
    }

    /**
     * 更新规则
     */
    public function update(Request $request, PromotionRule $promotionRule): JsonResponse
    {
        $this->authorizeTenant($request, $promotionRule);

        $validated = $request->validate([
            'name' => 'string|max:200',
            'description' => 'nullable|string|max:1000',
            'condition_type' => 'string|in:subtotal,quantity,items_count',
            'condition_value' => 'numeric|min:0',
            'discount_value' => 'numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'applicable_products' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'excluded_products' => 'nullable|array',
            'stackable_with_coupon' => 'boolean',
            'stackable_with_other_rules' => 'boolean',
            'priority' => 'integer|min:0|max:999',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'budget' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'tiers' => 'nullable|array',
            'tiers.*.from' => 'required|numeric|min:0',
            'tiers.*.to' => 'nullable|numeric',
            'tiers.*.type' => 'required|string|in:amount_off,percent_off',
            'tiers.*.value' => 'required|numeric|min:0',
            'buy_quantity' => 'nullable|integer|min:1',
            'free_quantity' => 'nullable|integer|min:1',
            'free_products' => 'nullable|array',
            'status' => 'string|in:draft,active,paused',
        ]);

        $promotionRule->update($validated);

        return ApiResponse::success($promotionRule->fresh()->load('creator:id,name'), __('app.common.updated'));
    }

    /**
     * 删除规则
     */
    public function destroy(Request $request, PromotionRule $promotionRule): JsonResponse
    {
        $this->authorizeTenant($request, $promotionRule);

        if ($promotionRule->redemptions()->count() > 0) {
            $promotionRule->update(['status' => 'expired']);
            return ApiResponse::success(null, __("app.promotion_engine.msg_ac8e98bb"));
        }

        $promotionRule->delete();
        return ApiResponse::success(null, __("app.promotion_engine.msg_0007d170"));
    }

    /**
     * 切换状态
     */
    public function toggleStatus(Request $request, PromotionRule $promotionRule): JsonResponse
    {
        $this->authorizeTenant($request, $promotionRule);

        $allowedTransitions = [
            'draft' => ['active'],
            'active' => ['paused', 'expired'],
            'paused' => ['active', 'expired'],
        ];

        $request->validate([
            'status' => 'required|string|in:' . implode(',', array_merge(...array_values($allowedTransitions))),
        ]);

        $newStatus = $request->status;
        $currentStatus = $promotionRule->status;

        if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [])) {
            return ApiResponse::error(__("app.promotion_engine.msg_d0ec6951"));
        }

        $promotionRule->update(['status' => $newStatus]);

        return ApiResponse::success($promotionRule->fresh(), __('app.common.status_switched', ['status' => $newStatus]));
    }

    /**
     * 计算折扣（预览，不记录使用）
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rule_id' => 'required|integer|exists:promotion_rules,id',
            'subtotal' => 'required|numeric|min:0',
            'item_count' => 'integer|min:0',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer',
        ]);

        $rule = PromotionRule::findOrFail($validated['rule_id']);

        $result = $this->promotionEngineService->calculateDiscount(
            $rule,
            (float) $validated['subtotal'],
            (int) ($validated['item_count'] ?? 0),
            $validated['product_ids'] ?? [],
            $validated['category_ids'] ?? [],
        );

        return ApiResponse::success($result);
    }

    /**
     * 应用促销（执行折扣并记录使用）
     */
    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rule_id' => 'required|integer|exists:promotion_rules,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'subtotal' => 'required|numeric|min:0',
            'item_count' => 'integer|min:0',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer',
        ]);

        $tenantId = $request->user()->tenant_id;
        $rule = PromotionRule::where('tenant_id', $tenantId)->findOrFail($validated['rule_id']);
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($validated['customer_id']);

        try {
            $result = $this->promotionEngineService->applyPromotion(
                $rule,
                $customer,
                (float) $validated['subtotal'],
                (int) ($validated['item_count'] ?? 0),
                $validated['product_ids'] ?? [],
                $validated['category_ids'] ?? [],
                ['applied_by' => $request->user()->id],
            );

            return ApiResponse::success($result, __("app.promotion_engine.msg_951ff69e"));
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * 查找最佳促销组合
     */
    public function bestPromotion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subtotal' => 'required|numeric|min:0',
            'item_count' => 'integer|min:0',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer',
        ]);

        $tenantId = $request->user()->tenant_id;
        $rules = PromotionRule::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('priority')
            ->get();

        $result = $this->promotionEngineService->findBestPromotion(
            $rules->all(),
            (float) $validated['subtotal'],
            (int) ($validated['item_count'] ?? 0),
            $validated['product_ids'] ?? [],
            $validated['category_ids'] ?? [],
        );

        return ApiResponse::success($result);
    }

    /**
     * 检查叠加性
     */
    public function checkStackability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rule_id' => 'required|integer|exists:promotion_rules,id',
            'has_coupon' => 'required|boolean',
        ]);

        $rule = PromotionRule::findOrFail($validated['rule_id']);

        return ApiResponse::success(
            $this->promotionEngineService->checkStackability($rule, $validated['has_coupon'])
        );
    }

    /**
     * 使用统计
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $stats = [
            'total_rules' => PromotionRule::where('tenant_id', $tenantId)->count(),
            'active_rules' => PromotionRule::where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'total_redemptions' => \App\Models\PromotionRuleRedemption::where('tenant_id', $tenantId)->count(),
            'total_discount_amount' => \App\Models\PromotionRuleRedemption::where('tenant_id', $tenantId)->sum('discount_amount'),
            'by_type' => PromotionRule::where('tenant_id', $tenantId)
                ->selectRaw('type, count(*) as count, sum(usage_count) as total_usage')
                ->groupBy('type')
                ->get(),
        ];

        return ApiResponse::success($stats);
    }

    /**
     * 验证租户归属
     */
    protected function authorizeTenant(Request $request, PromotionRule $rule): void
    {
        if ($rule->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }
    }
}
