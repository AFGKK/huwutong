<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentWebhookLog;
use App\Models\Invoice;
use App\Services\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 支付管理后台（M2-06）
 *
 * 支付渠道配置、交易流水、Webhook 日志查看。
 */
class PaymentAdminController extends Controller
{
    public function __construct(
        protected PaymentManager $paymentManager,
    ) {
    }

    /**
     * 支付概况统计
     */
    public function stats(): JsonResponse
    {
        $totalRevenue = Invoice::where('status', 'paid')->sum('amount');
        $pendingCount = Invoice::where('status', 'pending')->count();
        $failedCount = Invoice::where('status', 'failed')->count();
        $totalTransactions = Invoice::whereIn('status', ['paid', 'failed', 'refunded'])->count();

        // 最近30天收入
        $recentRevenue = Invoice::where('status', 'paid')
            ->where('paid_at', '>=', now()->subDays(30))
            ->sum('amount');

        // 各渠道交易数
        $channelStats = [
            'alipay' => Invoice::where('payment_method', 'alipay')->where('status', 'paid')->count(),
            'wechat' => Invoice::where('payment_method', 'wechat')->where('status', 'paid')->count(),
            'stripe' => Invoice::where('payment_method', 'stripe')->where('status', 'paid')->count(),
            'paypal' => Invoice::where('payment_method', 'paypal')->where('status', 'paid')->count(),
            'yipay' => Invoice::where('payment_method', 'yipay')->where('status', 'paid')->count(),
            'mock' => Invoice::where('payment_method', 'mock')->where('status', 'paid')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => round($totalRevenue, 2),
                'recent_revenue_30d' => round($recentRevenue, 2),
                'pending_count' => $pendingCount,
                'failed_count' => $failedCount,
                'total_transactions' => $totalTransactions,
                'channel_stats' => $channelStats,
                'gateway' => $this->paymentManager->gatewayName(),
            ],
        ]);
    }

    /**
     * 交易流水
     */
    public function transactions(Request $request): JsonResponse
    {
        $query = Invoice::with(['subscription.plan', 'customer'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('invoice_no', 'like', "%{$s}%")
                  ->orWhere('id', $s);
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $transactions = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * 交易详情
     */
    public function transactionDetail(int $id): JsonResponse
    {
        $invoice = Invoice::with(['subscription.plan', 'customer', 'items'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    /**
     * Webhook 日志
     */
    public function webhookLogs(Request $request): JsonResponse
    {
        $query = PaymentWebhookLog::orderBy('created_at', 'desc');

        if ($request->filled('gateway')) {
            $query->where('gateway', $request->input('gateway'));
        }
        if ($request->filled('event_type')) {
            $query->where('event_type', 'like', '%' . $request->input('event_type') . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $logs = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * 支付渠道配置状态
     */
    public function gatewayConfig(): JsonResponse
    {
        $config = config('payment.channels', []);
        $result = [];

        foreach ($config as $key => $channel) {
            $result[$key] = [
                'name' => $channel['name'] ?? $key,
                'enabled' => $channel['enabled'] ?? false,
                'configured' => !empty($channel['app_id'] ?? $channel['key'] ?? $channel['secret'] ?? ''),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 切换支付驱动（用于开发调试）
     */
    public function switchDriver(Request $request): JsonResponse
    {
        $request->validate(['driver' => 'required|in:mock,alipay,wechat,stripe,paypal,yipay']);

        // 仅开发环境允许切换
        if (!app()->environment('local', 'testing')) {
            return response()->json(['success' => false, 'message' => __('app.controller_compat.payment_admin_msg_167')], 403);
        }

        // 更新 .env 需要文件操作，此处仅返回提示
        return response()->json([
            'success' => true,
            'message' => __('app.controller_compat.payment_admin_env_payment_driver') . $request->input('driver'),
        ]);
    }
}
