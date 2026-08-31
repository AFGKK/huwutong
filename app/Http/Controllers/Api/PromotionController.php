<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\EnterpriseContract;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(
        protected PromotionService $service,
    ) {}

    // ═══════════════ 促销活动 ═══════════════

    public function index(Request $request): JsonResponse
    {
        $data = $this->service->listPromotions(
            $request->only(['status', 'type', 'search']),
            $request->input('per_page', 20)
        );
        return ApiResponse::success($data);
    }

    public function show(Promotion $promotion): JsonResponse
    {
        $promotion->load('creator:id,name');
        return ApiResponse::success($promotion);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:flash_sale,bulk_discount,bundle,x_for_y,free_gift,tiered',
            'description' => 'nullable|string',
            'discount_type' => 'nullable|in:percentage,fixed_amount,free',
            'discount_value' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'applicable_plans' => 'nullable|array',
            'applicable_products' => 'nullable|array',
            'applicable_billing_periods' => 'nullable|array',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'budget' => 'nullable|numeric|min:0',
            'rules' => 'nullable|array',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'display_config' => 'nullable|array',
            'metadata' => 'nullable|array',
            'slug' => 'nullable|string|max:255|unique:promotions,slug',
        ]);
        $promotion = $this->service->createPromotion($validated);
        return ApiResponse::success($promotion, __('app.api.promotion.created'), 201);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'nullable|in:percentage,fixed_amount,free',
            'discount_value' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'applicable_plans' => 'nullable|array',
            'applicable_products' => 'nullable|array',
            'applicable_billing_periods' => 'nullable|array',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'budget' => 'nullable|numeric|min:0',
            'rules' => 'nullable|array',
            'starts_at' => 'date',
            'ends_at' => 'nullable|date|after:starts_at',
            'display_config' => 'nullable|array',
        ]);
        $promotion = $this->service->updatePromotion($promotion, $validated);
        return ApiResponse::success($promotion, __('app.api.promotion.updated'));
    }

    public function publish(Promotion $promotion): JsonResponse
    {
        try {
            $promotion = $this->service->publishPromotion($promotion);
            return ApiResponse::success($promotion, __('app.api.promotion.published'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('PUBLISH_FAILED', $e->getMessage(), 400);
        }
    }

    public function pause(Promotion $promotion): JsonResponse
    {
        $promotion = $this->service->pausePromotion($promotion);
        return ApiResponse::success($promotion, __('app.api.promotion.paused'));
    }

    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->service->getPromotionStats());
    }

    // ═══════════════ 客户门户：可用促销 ═══════════════

    public function activePromotions(Request $request): JsonResponse
    {
        $customerId = $request->user()?->customer?->id;
        return ApiResponse::success($this->service->getActivePromotionsForCustomer($customerId));
    }

    // ═══════════════ 企业年框合同 ═══════════════

    public function contractIndex(Request $request): JsonResponse
    {
        $data = $this->service->listContracts(
            $request->only(['status', 'customer_id', 'search']),
            $request->input('per_page', 20)
        );
        return ApiResponse::success($data);
    }

    public function contractShow(EnterpriseContract $contract): JsonResponse
    {
        $contract->load(['customer:id,name', 'creator:id,name', 'approver:id,name', 'renewedContract:id,contract_number,name']);
        return ApiResponse::success($contract);
    }

    public function contractStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'customer_id' => 'required|integer|exists:customers,id',
            'total_value' => 'required|numeric|min:0',
            'currency' => 'string|size:3',
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'negotiated_amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'billing_cycle_days' => 'integer|min:1',
            'licensed_items' => 'required|array',
            'terms' => 'nullable|array',
            'special_terms' => 'nullable|array',
            'auto_renew' => 'boolean',
            'renewal_notice_days' => 'integer|min:1',
            'notes' => 'nullable|string',
        ]);
        $contract = $this->service->createContract($validated);
        return ApiResponse::success($contract, __('app.api.promotion.contract_created'), 201);
    }

    public function contractUpdate(Request $request, EnterpriseContract $contract): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'total_value' => 'numeric|min:0',
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'negotiated_amount' => 'nullable|numeric|min:0',
            'end_date' => 'date|after:start_date',
            'licensed_items' => 'array',
            'terms' => 'nullable|array',
            'special_terms' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);
        $contract = $this->service->updateContract($contract, $validated);
        return ApiResponse::success($contract, __('app.api.promotion.contract_updated'));
    }

    public function contractApprove(Request $request, EnterpriseContract $contract): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'notes' => 'nullable|string|max:1000',
        ]);
        $contract = $this->service->approveContract($contract, $validated['status'], $validated['notes'] ?? null);
        $msg = $validated['status'] === 'approved' ? __('app.api.promotion.contract_approved') : __('app.api.promotion.contract_rejected');
        return ApiResponse::success($contract, $msg);
    }

    public function contractStats(): JsonResponse
    {
        return ApiResponse::success($this->service->getContractStats());
    }

    // ═══════════════ 优惠券管理 ═══════════════

    public function couponIndex(Request $request): JsonResponse
    {
        $data = $this->service->listCoupons(
            $request->only(['status', 'type', 'search']),
            $request->input('per_page', 20)
        );
        return ApiResponse::success($data);
    }

    public function couponStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:coupons,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:percentage,fixed_amount,free_trial',
            'value' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'applicable_plans' => 'nullable|array',
            'applicable_products' => 'nullable|array',
            'applicable_billing_periods' => 'nullable|array',
            'is_redeemable_with_other_coupons' => 'boolean',
            'is_stackable' => 'boolean',
            'auto_apply' => 'boolean',
            'priority' => 'integer',
            'budget' => 'nullable|numeric|min:0',
            'promotion_id' => 'nullable|integer|exists:promotions,id',
            'starts_at' => 'required|date',
            'expires_at' => 'nullable|date|after:starts_at',
        ]);
        $coupon = $this->service->createCoupon($validated);
        return ApiResponse::success($coupon, __('app.api.promotion.coupon_created'), 201);
    }

    public function customerCoupons(Request $request): JsonResponse
    {
        $customerId = $request->user()?->customer?->id;
        if (!$customerId) {
            return ApiResponse::error('NO_CUSTOMER', __('app.api.promotion.no_customer'), 400);
        }
        return ApiResponse::success($this->service->getCustomerCoupons($customerId));
    }
}
