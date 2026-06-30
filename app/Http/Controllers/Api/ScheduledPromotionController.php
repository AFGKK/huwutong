<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\PromotionCalendarEvent;
use App\Models\SkuSpecialPrice;
use App\Services\ScheduledPromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 定时上下架+促销管理 (M2-151 🛒)
 */
class ScheduledPromotionController extends Controller
{
    public function __construct(
        protected ScheduledPromotionService $scheduledPromotionService,
    ) {}

    /**
     * 促销列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = Promotion::with('creator:id,name')
            ->orderByDesc('created_at');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = (int) $request->get('per_page', 20);
        return ApiResponse::paginated($query->paginate($perPage));
    }

    /**
     * 促销详情
     */
    public function show(int $id): JsonResponse
    {
        $promotion = Promotion::with(['creator:id,name', 'skuSpecialPrices.sku:id,name,sku_code,price'])
            ->findOrFail($id);
        return ApiResponse::success($promotion);
    }

    /**
     * 创建促销
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'type' => 'required|string|in:flash_sale,bulk_discount,bundle,x_for_y,free_gift,tiered',
            'description' => 'nullable|string',
            'discount_type' => 'nullable|string|in:percentage,fixed_amount,free',
            'discount_value' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'applicable_products' => 'nullable|array',
            'applicable_skus' => 'nullable|array',
            'usage_limit' => 'nullable|integer|min:0',
            'usage_limit_per_customer' => 'nullable|integer|min:0',
            'budget' => 'nullable|numeric|min:0',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'whitelist_customers' => 'nullable|array',
            'is_first_order_only' => 'boolean',
            'is_member_only' => 'boolean',
            'member_tier' => 'nullable|string|in:silver,gold,platinum',
            'auto_recover' => 'boolean',
            'display_config' => 'nullable|array',
            'sku_special_prices' => 'nullable|array',
        ]);

        try {
            $promotion = $this->scheduledPromotionService->createPromotion($data);
            return ApiResponse::success($promotion->load('creator:id,name'), '促销活动已创建');
        } catch (\Throwable $e) {
            return ApiResponse::error('CREATE_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * 更新促销
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);

        $data = $request->validate([
            'name' => 'string|max:200',
            'type' => 'string|in:flash_sale,bulk_discount,bundle,x_for_y,free_gift,tiered',
            'description' => 'nullable|string',
            'discount_type' => 'nullable|string|in:percentage,fixed_amount,free',
            'discount_value' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'applicable_products' => 'nullable|array',
            'applicable_skus' => 'nullable|array',
            'usage_limit' => 'nullable|integer|min:0',
            'usage_limit_per_customer' => 'nullable|integer|min:0',
            'budget' => 'nullable|numeric|min:0',
            'starts_at' => 'date',
            'ends_at' => 'nullable|date|after:starts_at',
            'whitelist_customers' => 'nullable|array',
            'is_first_order_only' => 'boolean',
            'is_member_only' => 'boolean',
            'member_tier' => 'nullable|string|in:silver,gold,platinum',
            'auto_recover' => 'boolean',
            'display_config' => 'nullable|array',
        ]);

        try {
            $promotion = $this->scheduledPromotionService->updatePromotion($promotion, $data);
            return ApiResponse::success($promotion, '促销活动已更新');
        } catch (\Throwable $e) {
            return ApiResponse::error('UPDATE_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * 发布活动
     */
    public function publish(int $id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);
        try {
            $promotion = $this->scheduledPromotionService->publish($promotion);
            return ApiResponse::success($promotion, '活动已发布');
        } catch (\Throwable $e) {
            return ApiResponse::error('PUBLISH_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * 暂停活动
     */
    public function pause(int $id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);
        try {
            $promotion = $this->scheduledPromotionService->pause($promotion);
            return ApiResponse::success($promotion, '活动已暂停');
        } catch (\Throwable $e) {
            return ApiResponse::error('PAUSE_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * 删除活动
     */
    public function destroy(int $id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->delete();
        return ApiResponse::success(null, '活动已删除');
    }

    /**
     * 活动日历事件
     */
    public function calendar(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->format('Y-m'));
        return ApiResponse::success(
            $this->scheduledPromotionService->getCalendarEvents($request->user()->tenant_id, $month)
        );
    }

    /**
     * 活动统计
     */
    public function stats(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->scheduledPromotionService->getStats($request->user()->tenant_id)
        );
    }

    /**
     * 客户可见的促销列表（前端商店页调用）
     */
    public function visiblePromotions(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer_id;
        return ApiResponse::success(
            $this->scheduledPromotionService->getVisiblePromotions($customerId)
        );
    }

    /**
     * 检查客户活动资格
     */
    public function checkEligibility(Request $request, int $promotionId): JsonResponse
    {
        $customerId = $request->user()->customer_id;
        $promotion = Promotion::findOrFail($promotionId);
        return ApiResponse::success(
            $this->scheduledPromotionService->checkCustomerEligibility($promotion, $customerId)
        );
    }
}
