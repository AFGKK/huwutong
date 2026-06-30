<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSku;
use App\Services\CartService;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\ProductSearchService;
use App\Services\RefundWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 电商API端点全套 (M2-152 🛒)
 *
 * 统一电商 API 入口：
 * - 商品列表/详情/SKU查询
 * - 购物车完整操作
 * - 下单/支付/取消
 * - 订单查询/统计
 * - 发货查询
 * - 退款申请
 * - 统一错误码+分页+筛选
 */
class EcommerceAPIController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected OrderService $orderService,
        protected InventoryService $inventoryService,
        protected RefundWorkflowService $refundWorkflow,
        protected ProductSearchService $productSearch,
    ) {}

    // ═══════════════ 商品 ═══════════════

    /**
     * 商品搜索/列表（公开，增强版 M2-156 🛒）
     *
     * 支持：全文搜索+标签筛选+价格区间+计费周期+多维排序+结果高亮
     */
    public function products(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->productSearch->search($request->all())
        );
    }

    /**
     * 搜索建议（自动补全）
     */
    public function productSuggest(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        return ApiResponse::success(
            $this->productSearch->suggest($query)
        );
    }

    /**
     * 热门搜索词
     */
    public function hotSearchTerms(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->productSearch->getHotSearchTerms()
        );
    }

    /**
     * 搜索历史
     */
    public function searchHistory(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->productSearch->getSearchHistory($request->user()?->id)
        );
    }

    /**
     * 清除搜索历史
     */
    public function clearSearchHistory(Request $request): JsonResponse
    {
        if ($user = $request->user()) {
            $this->productSearch->clearSearchHistory($user->id);
        }
        return ApiResponse::success(null, '搜索历史已清除');
    }

    /**
     * 获取筛选标签列表
     */
    public function filterTags(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->productSearch->getFilterTags()
        );
    }

    /**
     * 商品详情（含SKU列表）
     */
    public function productDetail(int $id, Request $request): JsonResponse
    {
        $product = Product::with(['skus' => function ($q) {
            $q->where('is_active', true);
        }])->findOrFail($id);

        return ApiResponse::success($product);
    }

    /**
     * SKU详情
     */
    public function skuDetail(int $id): JsonResponse
    {
        $sku = ProductSku::with('product')->findOrFail($id);
        return ApiResponse::success($sku);
    }

    // ═══════════════ 购物车 ═══════════════

    /**
     * 查看购物车
     */
    public function cartShow(Request $request): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart(
            $request->user()->id,
            $request->user()->tenant_id
        );
        return ApiResponse::success($this->cartService->getCartSummary($cart));
    }

    /**
     * 添加商品到购物车
     */
    public function cartAdd(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku_id' => 'required|exists:product_skus,id',
            'quantity' => 'integer|min:1|max:99',
        ]);

        $cart = $this->cartService->getOrCreateCart(
            $request->user()->id,
            $request->user()->tenant_id
        );

        try {
            $item = $this->cartService->addItem($cart, $data['sku_id'], $data['quantity'] ?? 1);
            return ApiResponse::success($item->load('sku.product'), '已添加到购物车');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CART_ADD_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 更新购物车商品数量
     */
    public function cartUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku_id' => 'required|exists:product_skus,id',
            'quantity' => 'required|integer|min:0|max:99',
        ]);

        try {
            $cart = \App\Models\Cart::firstOrCreate(['user_id' => $request->user()->id]);
            $this->cartService->updateQuantity($cart, $data['sku_id'], $data['quantity']);
            return ApiResponse::success(
                $this->cartService->getCartSummary($cart->fresh()),
                '购物车已更新'
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CART_UPDATE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 移除购物车商品
     */
    public function cartRemove(Request $request): JsonResponse
    {
        $data = $request->validate(['sku_id' => 'required|exists:product_skus,id']);
        $cart = \App\Models\Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $this->cartService->removeItem($cart, $data['sku_id']);
        }
        return ApiResponse::success(null, '已从购物车移除');
    }

    /**
     * 清空购物车
     */
    public function cartClear(Request $request): JsonResponse
    {
        $cart = \App\Models\Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $this->cartService->clear($cart);
        }
        return ApiResponse::success(null, '购物车已清空');
    }

    /**
     * 应用优惠券
     */
    public function cartApplyCoupon(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:50']);
        $cart = \App\Models\Cart::where('user_id', $request->user()->id)->first();
        if (!$cart) {
            return ApiResponse::error('CART_EMPTY', '购物车为空', 400);
        }
        try {
            $result = $this->cartService->applyCoupon($cart, $data['code']);
            return ApiResponse::success($result, '优惠券已应用');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('COUPON_ERROR', $e->getMessage(), 400);
        }
    }

    /**
     * 移除优惠券
     */
    public function cartRemoveCoupon(Request $request): JsonResponse
    {
        $cart = \App\Models\Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $this->cartService->removeCoupon($cart);
        }
        return ApiResponse::success(null, '优惠券已移除');
    }

    // ═══════════════ 订单 ═══════════════

    /**
     * 创建订单（含库存扣减+优惠券核销）
     */
    public function orderCreate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.sku_id' => 'required|exists:product_skus,id',
            'items.*.quantity' => 'integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'currency' => 'string|size:3',
            'billing_address' => 'nullable|array',
            'notes' => 'nullable|string|max:500',
            'coupon_code' => 'nullable|string',
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;
        $data['user_id'] = $request->user()->id;
        $data['customer_id'] = $request->user()->customer?->id;

        try {
            $order = $this->orderService->createOrder($data);
            return ApiResponse::created(
                $order->load('items.sku.product'),
                '订单创建成功'
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error('ORDER_CREATE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 订单列表
     */
    public function orderList(Request $request): JsonResponse
    {
        $filters = array_merge(
            $request->only(['status', 'date_from', 'date_to', 'search']),
            ['tenant_id' => $request->user()->tenant_id, 'user_id' => $request->user()->id]
        );

        return ApiResponse::paginated(
            $this->orderService->list($filters, $request->input('per_page', 20))
        );
    }

    /**
     * 订单详情
     */
    public function orderDetail(int $id, Request $request): JsonResponse
    {
        $order = Order::with(['items.sku.product', 'deliveries'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        return ApiResponse::success($order);
    }

    /**
     * 支付跳转
     */
    public function orderPay(int $id, Request $request): JsonResponse
    {
        $order = Order::with('items')
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        try {
            $gateway = $request->input('gateway', 'alipay');
            $result = $this->orderService->initiatePayment($order, $gateway);
            return ApiResponse::success($result, '支付请求已创建');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('PAYMENT_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 取消订单
     */
    public function orderCancel(int $id, Request $request): JsonResponse
    {
        $order = Order::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        try {
            $order = $this->orderService->cancel($order, $request->input('reason'));
            return ApiResponse::success($order->load('items.sku'), '订单已取消');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('ORDER_CANCEL_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 支付状态查询
     */
    public function orderPaymentStatus(int $id, Request $request): JsonResponse
    {
        $order = Order::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        return ApiResponse::success($this->orderService->getPaymentStatus($order));
    }

    /**
     * 订单统计
     */
    public function orderStats(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->orderService->getStats($request->user()->tenant_id)
        );
    }

    // ═══════════════ 发货 ═══════════════

    /**
     * 发货记录列表
     */
    public function deliveryList(Request $request): JsonResponse
    {
        $query = Delivery::with(['orderItem.order'])
            ->whereHas('orderItem.order', function ($q) use ($request) {
                $q->where('tenant_id', $request->user()->tenant_id);
            });

        if ($request->has('order_id')) {
            $query->whereHas('orderItem', fn($q) => $q->where('order_id', $request->input('order_id')));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        return ApiResponse::paginated($query->orderByDesc('id')->paginate($perPage));
    }

    /**
     * 发货详情
     */
    public function deliveryDetail(int $id, Request $request): JsonResponse
    {
        $delivery = Delivery::with(['orderItem.order', 'orderItem.sku.product'])
            ->findOrFail($id);

        // 权限检查
        if ($delivery->orderItem->order->tenant_id !== $request->user()->tenant_id) {
            return ApiResponse::error('FORBIDDEN', '无权访问', 403);
        }

        return ApiResponse::success($delivery);
    }

    // ═══════════════ 退款 ═══════════════

    /**
     * 申请退款
     */
    public function refundRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:2000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'url',
            'refund_type' => 'nullable|string|in:full,partial',
        ]);

        // 订单归属检查
        $order = Order::where('tenant_id', $request->user()->tenant_id)
            ->find($validated['order_id']);
        if (!$order) {
            return ApiResponse::error('ORDER_NOT_FOUND', '订单不存在', 404);
        }

        try {
            $refund = $this->refundWorkflow->requestRefund(
                $request->user()->customer?->id ?? $request->user()->id,
                $validated['order_id'],
                $validated
            );
            return ApiResponse::created($refund, '退款申请已提交');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('REFUND_REQUEST_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 我的退款记录
     */
    public function refundList(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->refundWorkflow->getRefunds(
                $request->user()->tenant_id,
                array_merge($request->all(), ['per_page' => 20])
            )
        );
    }
}
