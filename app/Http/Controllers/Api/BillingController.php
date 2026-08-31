<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceTitle;
use App\Models\License;
use App\Models\Order;
use App\Models\PricingPlan;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\AutoInvoiceService;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BillingController extends Controller
{
    public function __construct(
        protected BillingService $billingService,
        protected AutoInvoiceService $autoInvoiceService,
    ) {
    }

    /**
     * 订阅列表
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $query = Subscription::with(['customer.user:id,name', 'product:id,name']);

        // 筛选
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }
        if ($request->filled('plan')) {
            $query->where('plan', $request->input('plan'));
        }

        // 搜索
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', fn($cq) => $cq->where('name', 'like', "%{$search}%"))
                  ->orWhere('plan', 'like', "%{$search}%");
            });
        }

        $subscriptions = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }

    /**
     * 创建订阅（支持定价方案和优惠券）
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Subscription::class);

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'product_id' => 'required|exists:products,id',
            'plan_slug' => 'required|string|exists:pricing_plans,slug',
            'billing_period' => 'sometimes|in:monthly,quarterly,semi_annually,yearly',
            'auto_renew' => 'sometimes|boolean',
            'trial_days' => 'sometimes|integer|min:0|max:90',
            'grace_days' => 'sometimes|integer|min:0|max:90',
            'license_id' => 'sometimes|exists:licenses,id',
            'coupon_code' => 'sometimes|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $customer = Customer::findOrFail($request->input('customer_id'));
        $product = Product::findOrFail($request->input('product_id'));

        try {
            $subscription = $this->billingService->createSubscription(
                $customer,
                $product,
                $request->input('plan_slug'),
                $request->input('billing_period', 'monthly'),
                [
                    'auto_renew' => $request->input('auto_renew', true),
                    'trial_days' => $request->input('trial_days', 0),
                    'grace_days' => $request->input('grace_days', 7),
                    'license_id' => $request->input('license_id'),
                    'coupon_code' => $request->input('coupon_code'),
                ],
            );

            return response()->json([
                'success' => true,
                'message' => __('app.api.billing.subscription_created'),
                'data' => $subscription->load(['customer.user:id,name', 'product:id,name']),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 订阅详情
     */
    public function show(Subscription $subscription): JsonResponse
    {
        $this->authorize('view', $subscription);

        $subscription->load([
            'customer.user:id,name,email',
            'product:id,name',
            'invoices' => fn($q) => $q->latest()->limit(10),
        ]);

        return response()->json([
            'success' => true,
            'data' => $subscription,
        ]);
    }

    /**
     * 变更订阅套餐（支持定价方案切换）
     */
    public function changePlan(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        $validator = Validator::make($request->all(), [
            'plan_slug' => 'required|string|exists:pricing_plans,slug',
            'billing_period' => 'sometimes|in:monthly,quarterly,semi_annually,yearly',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $plan = PricingPlan::where('slug', $request->input('plan_slug'))
            ->where('is_active', true)
            ->firstOrFail();

        $billingPeriod = $request->input('billing_period', $subscription->billing_period);
        $price = $plan->getPrice($billingPeriod);

        if ($price <= 0) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.billing.no_pricing'),
            ], 422);
        }

        $updated = $this->billingService->changePlan(
            $subscription,
            $plan->slug,
            $price,
            $billingPeriod,
        );

        return response()->json([
            'success' => true,
            'message' => __('app.api.billing.plan_changed'),
            'data' => $updated,
        ]);
    }

    /**
     * 取消订阅
     */
    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        $validator = Validator::make($request->all(), [
            'reason' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $this->billingService->cancelSubscription($subscription, $request->input('reason'));

        return response()->json([
            'success' => true,
            'message' => __('app.api.billing.subscription_cancelled'),
            'data' => $subscription->fresh(),
        ]);
    }

    /**
     * 恢复已取消的订阅
     */
    public function resume(Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        if ($subscription->status === 'expired') {
            return response()->json([
                'success' => false,
                'message' => __('app.api.billing.expired_cannot_resume'),
            ], 422);
        }

        $this->billingService->resumeSubscription($subscription);

        return response()->json([
            'success' => true,
            'message' => __('app.api.billing.subscription_resumed'),
            'data' => $subscription->fresh(),
        ]);
    }

    /**
     * 暂停订阅
     */
    public function suspend(Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        if (!in_array($subscription->status, ['active', 'grace'])) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.billing.pause_active_only'),
            ], 422);
        }

        $subscription->suspend();

        // 停用关联的 License
        License::where('subscription_id', $subscription->id)
            ->whereIn('status', ['active'])
            ->update(['status' => 'suspended']);

        Log::info('Billing: subscription suspended', [
            'subscription_id' => $subscription->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('app.api.billing.subscription_paused'),
            'data' => $subscription->fresh(),
        ]);
    }

    /**
     * 手动续费
     */
    public function renew(Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        $result = $this->billingService->manualRenew($subscription);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => __('app.api.billing.renew_ok'),
                'data' => $subscription->fresh()->load('invoices'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __('app.api.billing.renew_failed', ['error' => $result['error'] ?? __('app.api.billing.unknown_error')]),
        ], 502);
    }

    /**
     * 发票列表
     */
    public function invoices(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $query = Invoice::with(['customer.user:id,name', 'subscription:id,plan']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }
        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->input('subscription_id'));
        }

        $invoices = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * 发票详情
     */
    public function showInvoice(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $invoice->load(['customer.user:id,name', 'subscription:id,plan']);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    /**
     * 标记发票为已支付（支付回调模拟）
     */
    public function markPaid(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $result = $this->billingService->markInvoiceAsPaid(
            $invoice,
            $request->input('transaction_id'),
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? __('app.api.billing.invoice_paid') : __('app.api.billing.operation_failed'),
        ]);
    }

    /**
     * 统计概览
     */
    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        return response()->json([
            'success' => true,
            'data' => $this->billingService->getStats(),
        ]);
    }

    /**
     * 获取发票统计
     */
    public function invoiceStats(): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $totalRevenue = Invoice::where('status', 'paid')->sum('amount');
        $pendingAmount = Invoice::where('status', 'pending')->sum('amount');
        $thisMonth = Invoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $byStatus = Invoice::selectRaw('status, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('status')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => round((float) $totalRevenue, 2),
                'pending_amount' => round((float) $pendingAmount, 2),
                'this_month_revenue' => round((float) $thisMonth, 2),
                'by_status' => $byStatus,
            ],
        ]);
    }

    // ========================
    // 定价方案管理
    // ========================

    /**
     * 公开定价方案列表（给前端选购）
     */
    public function publicPlans(): JsonResponse
    {
        $plans = $this->billingService->getPublicPlans();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * 所有定价方案（管理端）
     */
    public function plans(Request $request): JsonResponse
    {
        $query = PricingPlan::with('product:id,name');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $plans = $query->ordered()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * 创建定价方案
     */
    public function storePlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'required|string|max:100|unique:pricing_plans,slug',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'product_id' => 'sometimes|exists:products,id',
            'currency' => 'sometimes|string|size:3',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_quarterly' => 'nullable|numeric|min:0',
            'price_semi_annually' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'features' => 'nullable|array',
            'limits' => 'nullable|array',
            'trial_days' => 'sometimes|integer|min:0',
            'is_public' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'badge' => 'nullable|string|max:50',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = auth()->user()->tenant_id;

        $plan = PricingPlan::create($data);

        return response()->json([
            'success' => true,
            'message' => __('app.api.billing.pricing_created'),
            'data' => $plan->load('product:id,name'),
        ], 201);
    }

    /**
     * 更新定价方案
     */
    public function updatePlan(Request $request, PricingPlan $plan): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'product_id' => 'sometimes|exists:products,id',
            'currency' => 'sometimes|string|size:3',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_quarterly' => 'nullable|numeric|min:0',
            'price_semi_annually' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'features' => 'nullable|array',
            'limits' => 'nullable|array',
            'trial_days' => 'sometimes|integer|min:0',
            'is_public' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'badge' => 'nullable|string|max:50',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $plan->update($request->all());

        return response()->json([
            'success' => true,
            'message' => __('app.api.billing.pricing_updated'),
            'data' => $plan->fresh()->load('product:id,name'),
        ]);
    }

    /**
     * 删除定价方案
     */
    public function destroyPlan(PricingPlan $plan): JsonResponse
    {
        if ($plan->subscriptions()->whereIn('status', ['active', 'grace'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.billing.pricing_has_subs'),
            ], 422);
        }

        $plan->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => __('app.api.billing.pricing_disabled'),
        ]);
    }

    // ========================
    // 优惠券管理
    // ========================

    /**
     * 优惠券列表
     */
    public function coupons(Request $request): JsonResponse
    {
        $query = Coupon::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        } elseif ($request->filled('code')) {
            $query->where('code', 'like', "%{$request->input('code')}%");
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $coupons = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $coupons,
        ]);
    }

    /**
     * 创建优惠券
     */
    public function storeCoupon(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:coupons,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:percentage,fixed_amount,free_trial,custom',
            'value' => 'required|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'usage_limit_per_user' => 'nullable|integer|min:0',
            'applicable_plans' => 'nullable|array',
            'applicable_plans.*' => 'string',
            'applicable_products' => 'nullable|array',
            'applicable_products.*' => 'integer',
            'applicable_billing_periods' => 'nullable|array',
            'applicable_billing_periods.*' => 'string',
            'is_redeemable_with_other_coupons' => 'sometimes|boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        // 清理 0 值字段，让数据库使用默认值
        if (isset($data['usage_limit']) && (int)$data['usage_limit'] <= 0) unset($data['usage_limit']);
        if (isset($data['usage_limit_per_user']) && (int)$data['usage_limit_per_user'] <= 0) unset($data['usage_limit_per_user']);
        if (isset($data['minimum_order_amount']) && (float)$data['minimum_order_amount'] <= 0) unset($data['minimum_order_amount']);
        if (isset($data['maximum_discount']) && (float)$data['maximum_discount'] <= 0) unset($data['maximum_discount']);

        $data['tenant_id'] = auth()->user()->tenant_id;

        $coupon = Coupon::create($data);

        return response()->json([
            'success' => true,
            'message' => __('app.api.billing.coupon_created'),
            'data' => $coupon,
        ], 201);
    }

    /**
     * 更新优惠券
     */
    public function updateCoupon(Request $request, Coupon $coupon): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:percentage,fixed_amount,free_trial,custom',
            'value' => 'sometimes|numeric|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'usage_limit_per_user' => 'nullable|integer|min:0',
            'applicable_plans' => 'nullable|array',
            'applicable_products' => 'nullable|array',
            'applicable_billing_periods' => 'nullable|array',
            'is_redeemable_with_other_coupons' => 'sometimes|boolean',
            'status' => 'sometimes|in:active,expired,disabled',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        // 清理 0 值字段
        if (isset($data['usage_limit']) && (int)$data['usage_limit'] <= 0) unset($data['usage_limit']);
        if (isset($data['usage_limit_per_user']) && (int)$data['usage_limit_per_user'] <= 0) unset($data['usage_limit_per_user']);
        if (isset($data['minimum_order_amount']) && (float)$data['minimum_order_amount'] <= 0) unset($data['minimum_order_amount']);
        if (isset($data['maximum_discount']) && (float)$data['maximum_discount'] <= 0) unset($data['maximum_discount']);

        $coupon->update($data);

        return response()->json([
            'success' => true,
            'message' => __('app.api.billing.coupon_updated'),
            'data' => $coupon->fresh(),
        ]);
    }

    /**
     * 校验优惠券（前端下单时预览）
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0',
            'plan' => 'sometimes|string|max:100',
            'product_id' => 'sometimes|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $result = $this->billingService->previewCoupon(
            $request->input('code'),
            (float) $request->input('amount'),
            $request->input('plan'),
            $request->input('product_id'),
        );

        return response()->json([
            'success' => $result['valid'],
            'data' => $result,
        ]);
    }

    /**
     * 优惠券使用记录
     */
    public function couponRedemptions(Request $request, Coupon $coupon): JsonResponse
    {
        $redemptions = CouponRedemption::where('coupon_id', $coupon->id)
            ->with('customer.user:id,name', 'subscription:id,plan')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $redemptions,
        ]);
    }

    /**
     * 优惠券统计
     */
    public function couponStats(): JsonResponse
    {
        $stats = [];
        $now = now();

        $stats['total'] = Coupon::count();
        $stats['active'] = Coupon::where('status', 'active')->count();
        $stats['expired'] = Coupon::where('status', 'expired')->orWhere(function ($q) use ($now) {
            $q->where('status', 'active')->whereNotNull('expires_at')->where('expires_at', '<', $now);
        })->count();

        $stats['total_redemptions'] = CouponRedemption::count();
        $stats['total_discount_amount'] = (float) CouponRedemption::sum('discount_amount');
        $stats['recent_30d_redemptions'] = CouponRedemption::where('created_at', '>=', $now->copy()->subDays(30))->count();
        $stats['recent_30d_discount'] = (float) CouponRedemption::where('created_at', '>=', $now->copy()->subDays(30))->sum('discount_amount');

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    // ═══════════════════════════
    // 自动开票（合并自 AutoInvoiceController）
    // ═══════════════════════════

    /**
     * 从订单生成发票
     */
    public function generateFromOrder(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with('items')
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($orderId);

        if ($order->status !== 'paid') {
            return \App\Http\ApiResponse::error('ORDER_NOT_PAID', __('app.api.auto_invoice.order_not_paid'), 400);
        }

        if ($order->invoice_id) {
            return \App\Http\ApiResponse::error('INVOICE_EXISTS', __('app.api.auto_invoice.invoice_exists'), 400);
        }

        $validated = $request->validate([
            'invoice_title_id' => 'nullable|exists:invoice_titles,id',
        ]);

        try {
            $invoice = $this->autoInvoiceService->generateFromOrder(
                $order,
                $validated['invoice_title_id'] ?? null
            );
            return \App\Http\ApiResponse::success($invoice->load('lineItems'), __('app.api.auto_invoice.invoice_generated'));
        } catch (\Throwable $e) {
            return \App\Http\ApiResponse::error('INVOICE_GENERATE_FAILED', __('app.api.auto_invoice.invoice_generate_failed', ['error' => $e->getMessage()]), 500);
        }
    }

    /**
     * 租户发票列表
     */
    public function tenantInvoices(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return \App\Http\ApiResponse::paginated(
            $this->autoInvoiceService->getTenantInvoices($tenantId, $request->all())
        );
    }

    /**
     * 租户发票详情
     */
    public function tenantInvoiceDetail(Request $request, int $invoiceId): JsonResponse
    {
        $invoice = Invoice::with([
            'lineItems', 'customer',
            'customer.user:id,name,email',
        ])->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($invoiceId);

        return \App\Http\ApiResponse::success($invoice);
    }

    /**
     * 发票 HTML 预览
     */
    public function invoicePreview(Request $request, int $invoiceId): \Illuminate\Http\Response
    {
        $invoice = Invoice::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($invoiceId);

        $html = $this->autoInvoiceService->getInvoiceHtml($invoice->id);
        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * 重发发票邮件
     */
    public function resendInvoice(Request $request, int $invoiceId): JsonResponse
    {
        $invoice = Invoice::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($invoiceId);

        try {
            $this->autoInvoiceService->resendInvoiceEmail($invoice->id);
            return \App\Http\ApiResponse::success(null, __('app.api.auto_invoice.invoice_resent'));
        } catch (\Throwable $e) {
            return \App\Http\ApiResponse::error('RESEND_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * 自动开票统计（合并原 AutoInvoiceController::stats + BillingController::invoiceStats）
     */
    public function autoInvoiceStats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $autoStats = $this->autoInvoiceService->getStats($tenantId);

        return \App\Http\ApiResponse::success($autoStats);
    }

    // ═══════════════ 发票抬头管理 ═══════════════

    /**
     * 解析客户 ID
     */
    protected function resolveInvoiceCustomerId($user): int
    {
        if ($user->customer_id) {
            return $user->customer_id;
        }
        $customer = Customer::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->first();
        return $customer?->id ?? 0;
    }

    /**
     * 发票抬头列表
     */
    public function invoiceTitles(Request $request): JsonResponse
    {
        $customerId = $this->resolveInvoiceCustomerId($request->user());

        $titles = InvoiceTitle::where('customer_id', $customerId)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
        return \App\Http\ApiResponse::success($titles);
    }

    /**
     * 创建发票抬头
     */
    public function storeInvoiceTitle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'tax_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:300',
            'phone' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:200',
            'bank_account' => 'nullable|string|max:50',
            'is_default' => 'boolean',
        ]);

        $user = $request->user();
        $customerId = $this->resolveInvoiceCustomerId($user);

        if (!$customerId) {
            return \App\Http\ApiResponse::error('MISSING_CUSTOMER', __('app.api.auto_invoice.missing_customer'), 400);
        }

        $data['customer_id'] = $customerId;
        $data['tenant_id'] = $user->tenant_id;

        if (!empty($data['is_default'])) {
            InvoiceTitle::where('customer_id', $customerId)
                ->update(['is_default' => false]);
        }

        $title = InvoiceTitle::create($data);
        return \App\Http\ApiResponse::success($title, __('app.api.auto_invoice.title_created'));
    }

    /**
     * 更新发票抬头
     */
    public function updateInvoiceTitle(Request $request, int $titleId): JsonResponse
    {
        $title = InvoiceTitle::where('customer_id', $request->user()->customer_id)
            ->findOrFail($titleId);

        $data = $request->validate([
            'title' => 'string|max:200',
            'tax_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:300',
            'phone' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:200',
            'bank_account' => 'nullable|string|max:50',
            'is_default' => 'boolean',
        ]);

        if (!empty($data['is_default'])) {
            InvoiceTitle::where('customer_id', $request->user()->customer_id)
                ->where('id', '!=', $titleId)
                ->update(['is_default' => false]);
        }

        $title->update($data);
        return \App\Http\ApiResponse::success($title->fresh(), __('app.api.auto_invoice.title_updated'));
    }

    /**
     * 删除发票抬头
     */
    public function destroyInvoiceTitle(Request $request, int $titleId): JsonResponse
    {
        $title = InvoiceTitle::where('customer_id', $request->user()->customer_id)
            ->findOrFail($titleId);
        $title->delete();
        return \App\Http\ApiResponse::success(null, __('app.api.auto_invoice.title_deleted'));
    }
}
