<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BillingCycle;
use App\Models\Order;
use App\Models\ProductSku;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 订单API完整版 (M2-146 🛒)
 *
 * - 订单创建（含库存扣减+优惠券核销）
 * - 支付跳转（生成支付链接）
 * - 取消订单（含库存回滚+优惠券回滚）
 * - 订单支付状态查询
 * - 超时自动取消
 * - 订单统计
 */
class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    /**
     * SKU列表（公开-商品展示用，带 ?admin=1 则显示全部）
     * 支持商店筛选：search, product_id, billing_cycle, price_min, price_max, sort, tags
     */
    public function skus(Request $request): JsonResponse
    {
        $query = ProductSku::with(['product.category', 'product.reviews' => function ($q) {
            $q->where('status', 'approved')->select('product_id', 'rating', 'id');
        }]);

        // 非管理员模式只显示有库存的上架SKU
        if (!$request->boolean('admin')) {
            $query->where('is_active', true)
                ->where(function ($q) {
                    $q->where('stock', '>', 0)->orWhere('stock', -1);
                });
        }

        // 搜索（匹配商品名称+SKU名称+描述）
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku_code', 'like', "%{$search}%")
                  ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%"));
            });
        }

        // 按产品筛选
        if ($request->has('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        // 按分类筛选
        if ($categoryId = $request->input('category_id')) {
            $query->whereHas('product', fn($q) => $q->where('category_id', $categoryId));
        }

        // 按计费周期筛选
        if ($cycle = $request->input('billing_cycle')) {
            $query->where('billing_cycle', $cycle);
        }

        // 按价格区间筛选
        if ($min = $request->input('price_min')) {
            $query->where('price', '>=', (float) $min);
        }
        if ($max = $request->input('price_max')) {
            $query->where('price', '<=', (float) $max);
        }

        // 按标签筛选
        if ($tags = $request->input('tags')) {
            $tagArray = explode(',', $tags);
            $query->whereHas('product.tags', fn($tq) => $tq->whereIn('tags.id', $tagArray));
        }

        // 排序
        $sortField = $request->input('sort', '-sold_count');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');
        $allowedSorts = ['price', 'sold_count', 'created_at', 'name'];
        if (in_array($field, $allowedSorts)) {
            if ($field === 'name') {
                $query->orderBy('name', $direction);
            } else {
                $query->orderBy($field, $direction);
            }
        } else {
            $query->orderBy('sold_count', 'desc');
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::success($query->paginate($perPage));
    }

    /**
     * 创建 SKU
     */
    public function storeSku(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku_code' => 'required|string|max:100|unique:product_skus,sku_code',
            'name' => 'required|string|max:255',
            'image_url' => 'nullable|string|max:500',
            'specs' => 'nullable|array',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'currency' => 'string|size:3',
            'stock' => 'integer|min:-1',
            'billing_cycle' => ['nullable', 'string', Rule::in(BillingCycle::activeCodes())],
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        $sku = ProductSku::create($validated);

        return ApiResponse::created($sku->load('product'), 'SKU 创建成功');
    }

    /**
     * 更新 SKU
     */
    public function updateSku(int $id, Request $request): JsonResponse
    {
        $sku = ProductSku::findOrFail($id);

        $validated = $request->validate([
            'product_id' => 'sometimes|exists:products,id',
            'sku_code' => 'sometimes|string|max:100|unique:product_skus,sku_code,' . $id,
            'name' => 'sometimes|string|max:255',
            'image_url' => 'nullable|string|max:500',
            'specs' => 'nullable|array',
            'price' => 'sometimes|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'currency' => 'string|size:3',
            'stock' => 'integer|min:-1',
            'billing_cycle' => ['nullable', 'string', Rule::in(BillingCycle::activeCodes())],
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        $sku->update($validated);
        return ApiResponse::success($sku->fresh()->load('product'), 'SKU 更新成功');
    }

    /**
     * 删除 SKU
     */
    public function destroySku(int $id): JsonResponse
    {
        $sku = ProductSku::findOrFail($id);
        $sku->delete();

        return ApiResponse::success(null, 'SKU 已删除');
    }

    /**
     * 创建订单（含库存扣减+优惠券核销）
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.sku_id' => 'required|exists:product_skus,id',
            'items.*.quantity' => 'integer|min:1',
            'items.*.item_type' => 'string|in:license,subscription,addon',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'discount_amount' => 'numeric|min:0',
            'currency' => 'string|size:3',
            'billing_address' => 'nullable|array',
            'notes' => 'nullable|string|max:500',
            'coupon_code' => 'nullable|string',
            'auto_renew' => 'boolean',
            'contact' => 'nullable|array',
            'contact.email' => 'required_with:contact|email|max:255',
            'contact.phone' => 'nullable|string|max:20',
            'contact.name' => 'nullable|string|max:100',
            'invoice' => 'nullable|array',
            'invoice.type' => 'required_with:invoice|string|in:personal,company',
            'invoice.title' => 'required_with:invoice|string|max:200',
            'invoice.tax_no' => 'nullable|string|max:18',
            'invoice.address' => 'nullable|string|max:200',
            'invoice.phone' => 'nullable|string|max:20',
            'invoice.bank' => 'nullable|string|max:100',
            'invoice.bank_account' => 'nullable|string|max:50',
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;
        $data['user_id'] = $request->user()->id;
        $data['customer_id'] = $request->user()->customer?->id;
        // 将发票数据、自动续费标识、联系信息存入 billing_address
        $billingExtra = [];
        if ($request->has('invoice') && !empty($request->input('invoice.title'))) {
            $billingExtra['invoice'] = $request->input('invoice');
        }
        if ($request->has('auto_renew')) {
            $billingExtra['auto_renew'] = (bool) $request->input('auto_renew');
        }
        if ($request->has('contact') && !empty($request->input('contact.email'))) {
            $billingExtra['contact'] = $request->input('contact');
        }
        if (!empty($billingExtra)) {
            $data['billing_address'] = array_merge(
                $data['billing_address'] ?? [],
                $billingExtra
            );
        }

        try {
            $order = $this->orderService->createOrder($data);
            return ApiResponse::created(
                $order->load('items.sku.product'),
                '订单创建成功'
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error('ORDER_CREATE_FAILED', $e->getMessage(), 400);
        } catch (\Exception $e) {
            return ApiResponse::error('ORDER_CREATE_FAILED', '订单创建失败', 500);
        }
    }

    /**
     * 订单详情
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $order = Order::with(['items.sku.product', 'deliveries'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        return ApiResponse::success($order);
    }

    /**
     * 订单列表
     */
    public function index(Request $request): JsonResponse
    {
        $filters = array_merge(
            $request->only(['status', 'date_from', 'date_to', 'search']),
            ['tenant_id' => $request->user()->tenant_id]
        );

        return ApiResponse::paginated(
            $this->orderService->list($filters, $request->input('per_page', 20))
        );
    }

    /**
     * 订单统计
     */
    public function stats(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->orderService->getStats($request->user()->tenant_id)
        );
    }

    /**
     * 取消订单（含库存回滚+优惠券回滚）
     */
    public function cancel(int $id, Request $request): JsonResponse
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
     * 支付跳转（生成支付链接）
     */
    public function pay(int $id, Request $request): JsonResponse
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
     * 支付回调（内部使用，标记订单已支付）
     */
    public function markPaid(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => 'required|string',
            'transaction_id' => 'required|string',
        ]);

        $order = Order::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        try {
            $order = $this->orderService->markPaid(
                $order,
                $data['payment_method'],
                $data['transaction_id'],
                $request->except(['payment_method', 'transaction_id'])
            );
            return ApiResponse::success($order->load('items.sku'), '支付成功');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('PAY_MARK_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 查询支付状态
     */
    public function paymentStatus(int $id, Request $request): JsonResponse
    {
        $order = Order::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        return ApiResponse::success(
            $this->orderService->getPaymentStatus($order)
        );
    }

    /**
     * 客户门户-我的订单
     */
    public function myOrders(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ];

        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }

        return ApiResponse::paginated(
            $this->orderService->list($filters, $request->input('per_page', 20))
        );
    }
}
