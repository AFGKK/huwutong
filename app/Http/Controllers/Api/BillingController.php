<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BillingController extends Controller
{
    public function __construct(
        protected BillingService $billingService,
    ) {}

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
     * 创建订阅
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Subscription::class);

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'product_id' => 'required|exists:products,id',
            'plan' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'billing_period' => 'sometimes|in:monthly,quarterly,semi_annually,yearly',
            'currency' => 'sometimes|string|size:3',
            'auto_renew' => 'sometimes|boolean',
            'trial_days' => 'sometimes|integer|min:0|max:90',
            'grace_days' => 'sometimes|integer|min:0|max:90',
            'license_id' => 'sometimes|exists:licenses,id',
            'pricing_plan_slug' => 'sometimes|string|max:100',
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
                $request->input('plan'),
                (float) $request->input('price'),
                $request->input('billing_period', 'monthly'),
                [
                    'currency' => $request->input('currency', 'CNY'),
                    'auto_renew' => $request->input('auto_renew', true),
                    'trial_days' => $request->input('trial_days', 0),
                    'grace_days' => $request->input('grace_days', 7),
                    'license_id' => $request->input('license_id'),
                    'pricing_plan_slug' => $request->input('pricing_plan_slug'),
                ],
            );

            return response()->json([
                'success' => true,
                'message' => '订阅创建成功',
                'data' => $subscription->load(['customer.user:id,name', 'product:id,name']),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '创建订阅失败: ' . $e->getMessage(),
            ], 500);
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
     * 变更订阅套餐
     */
    public function changePlan(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        $validator = Validator::make($request->all(), [
            'plan' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'billing_period' => 'sometimes|in:monthly,quarterly,semi_annually,yearly',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $updated = $this->billingService->changePlan(
            $subscription,
            $request->input('plan'),
            (float) $request->input('price'),
            $request->input('billing_period'),
        );

        return response()->json([
            'success' => true,
            'message' => '套餐已变更',
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
            'message' => '订阅已取消（当前周期结束后不再续费）',
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
                'message' => '已过期的订阅无法恢复，请重新创建订阅',
            ], 422);
        }

        $this->billingService->resumeSubscription($subscription);

        return response()->json([
            'success' => true,
            'message' => '订阅已恢复',
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
                'message' => '续费成功',
                'data' => $subscription->fresh()->load('invoices'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => '续费失败: ' . ($result['error'] ?? '未知错误'),
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
            'message' => $result ? '发票已标记为已支付' : '操作失败',
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
}
