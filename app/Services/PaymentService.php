<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 支付记录管理服务 (M1.1-27)
 *
 * 集中管理 payments 表的查询、统计与退款操作。
 */
class PaymentService
{
    /**
     * 支付成功后写入 payments（按 transaction_id 幂等）
     */
    public function recordCompleted(Invoice $invoice, array $paymentInfo, ?Order $order = null): Payment
    {
        $transactionId = $paymentInfo['transaction_id']
            ?? $paymentInfo['charge_id']
            ?? null;
        $transactionId = $transactionId !== null && $transactionId !== ''
            ? (string) $transactionId
            : null;

        if ($transactionId) {
            $existing = Payment::where('transaction_id', $transactionId)->first();
            if ($existing) {
                return $existing;
            }
        }

        $channel = $paymentInfo['payment_method'] ?? $paymentInfo['channel'] ?? 'unknown';

        $existingByInvoice = Payment::where('channel', $channel)
            ->where('metadata->invoice_id', $invoice->id)
            ->whereIn('status', ['completed', 'partially_refunded', 'refunded'])
            ->orderByDesc('id')
            ->first();
        if ($existingByInvoice) {
            return $existingByInvoice;
        }

        $orderId = $paymentInfo['order_id']
            ?? $order?->id
            ?? ($invoice->metadata['order_id'] ?? null);

        if (! $orderId && str_starts_with((string) $invoice->invoice_no, 'INV-')) {
            $orderNo = substr($invoice->invoice_no, 4);
            $orderId = Order::where('order_no', $orderNo)->value('id');
        }

        $amount = (float) ($paymentInfo['amount'] ?? $invoice->amount);

        $payment = Payment::create([
            'tenant_id' => $invoice->tenant_id,
            'order_id' => $orderId,
            'customer_id' => $invoice->customer_id,
            'channel' => $channel,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $invoice->currency ?? 'CNY',
            'fee' => 0,
            'net_amount' => $amount,
            'status' => 'completed',
            'paid_at' => now(),
            'description' => 'Invoice '.$invoice->invoice_no,
            'metadata' => array_merge(['invoice_id' => $invoice->id], $paymentInfo),
        ]);

        $this->clearDashboardCache();

        return $payment;
    }

    /**
     * 无发票时按订单金额写入 payments
     */
    public function recordCompletedForOrder(Order $order, array $paymentInfo): Payment
    {
        $transactionId = $paymentInfo['transaction_id'] ?? null;
        $transactionId = $transactionId !== null && $transactionId !== ''
            ? (string) $transactionId
            : null;

        if ($transactionId) {
            $existing = Payment::where('transaction_id', $transactionId)->first();
            if ($existing) {
                return $existing;
            }
        }

        $channel = $paymentInfo['payment_method'] ?? $paymentInfo['channel'] ?? $order->payment_channel ?? 'unknown';
        $amount = (float) ($paymentInfo['amount'] ?? $order->final_amount);

        $payment = Payment::create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'user_id' => $order->user_id,
            'channel' => $channel,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $order->currency ?? 'CNY',
            'fee' => 0,
            'net_amount' => $amount,
            'status' => 'completed',
            'paid_at' => now(),
            'description' => 'Order '.$order->order_no,
            'metadata' => array_merge(['order_no' => $order->order_no], $paymentInfo),
        ]);

        $this->clearDashboardCache();

        return $payment;
    }

    /**
     * 按交易号或发票关联将 Payment 标记为退款/部分退款
     */
    public function markRefundedByTransactionOrInvoice(
        ?string $transactionId = null,
        ?int $invoiceId = null,
        float $refundAmount = 0
    ): ?Payment {
        $payment = null;

        if ($transactionId) {
            $payment = Payment::where('transaction_id', $transactionId)->first();
        }

        if (! $payment && $invoiceId) {
            $payment = Payment::where('metadata->invoice_id', $invoiceId)
                ->whereIn('status', ['completed', 'partially_refunded'])
                ->orderByDesc('id')
                ->first();
        }

        if (! $payment) {
            return null;
        }

        $amount = $refundAmount > 0 ? $refundAmount : (float) $payment->amount;
        $payment->refunded_amount = (float) $payment->refunded_amount + $amount;
        $payment->refunded_at = now();

        if ($payment->refunded_amount >= $payment->amount) {
            $payment->status = 'refunded';
            $payment->refunded_amount = $payment->amount;
        } else {
            $payment->status = 'partially_refunded';
        }

        $payment->save();
        $this->clearDashboardCache();

        return $payment;
    }

    // ─── 仪表盘 ────────────────────────────────

    /**
     * 获取支付仪表盘统计数据
     */
    public function getDashboard(): array
    {
        $cacheKey = 'payment:dashboard';
        $ttl = 300;

        return Cache::remember($cacheKey, $ttl, function () {
            $today = now()->startOfDay();

            $totalRevenue = Payment::where('status', 'completed')->sum('amount');
            $todayRevenue = Payment::where('status', 'completed')
                ->where('paid_at', '>=', $today)
                ->sum('amount');
            $pendingCount = Payment::where('status', 'pending')->count();
            $completedCount = Payment::where('status', 'completed')->count();
            $failedCount = Payment::where('status', 'failed')->count();
            $refundedCount = Payment::whereIn('status', ['refunded', 'partially_refunded'])->count();
            $totalRefunded = Payment::whereIn('status', ['refunded', 'partially_refunded'])->sum('refunded_amount');

            // 渠道分布
            $channelStats = Payment::select('channel', DB::raw('count(*) as total'), DB::raw('sum(amount) as total_amount'))
                ->where('status', 'completed')
                ->groupBy('channel')
                ->get()
                ->keyBy('channel')
                ->toArray();

            return [
                'total_revenue' => round($totalRevenue, 2),
                'today_revenue' => round($todayRevenue, 2),
                'pending_count' => $pendingCount,
                'completed_count' => $completedCount,
                'failed_count' => $failedCount,
                'refunded_count' => $refundedCount,
                'total_refunded' => round($totalRefunded, 2),
                'channel_stats' => $channelStats,
            ];
        });
    }

    /**
     * 清除仪表盘缓存
     */
    public function clearDashboardCache(): void
    {
        Cache::forget('payment:dashboard');
    }

    // ─── 支付记录查询 ────────────────────────────

    /**
     * 分页查询支付记录
     */
    public function getPayments(array $params = []): array
    {
        $query = Payment::with(['tenant', 'order', 'user', 'customer'])
            ->orderByDesc('created_at');

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['channel'])) {
            $query->where('channel', $params['channel']);
        }
        if (!empty($params['search'])) {
            $s = $params['search'];
            $query->where(function ($q) use ($s) {
                $q->where('transaction_id', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }
        if (!empty($params['date_from'])) {
            $query->where('created_at', '>=', $params['date_from']);
        }
        if (!empty($params['date_to'])) {
            $query->where('created_at', '<=', $params['date_to'] . ' 23:59:59');
        }
        if (!empty($params['amount_min'])) {
            $query->where('amount', '>=', $params['amount_min']);
        }
        if (!empty($params['amount_max'])) {
            $query->where('amount', '<=', $params['amount_max']);
        }
        if (!empty($params['tenant_id'])) {
            $query->where('tenant_id', $params['tenant_id']);
        }

        $perPage = min((int) ($params['per_page'] ?? 15), 100);
        $page = (int) ($params['page'] ?? 1);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 获取单条支付记录详情
     */
    public function getPaymentDetail(int $id): ?Payment
    {
        return Payment::with(['tenant', 'order', 'user', 'customer'])->find($id);
    }

    // ─── 退款操作 ────────────────────────────────

    /**
     * 执行退款
     */
    public function refund(int $id, ?float $amount = null): array
    {
        $payment = Payment::findOrFail($id);

        if (!in_array($payment->status, ['completed', 'partially_refunded'])) {
            return ['success' => false, 'message' => __('app.common.current_status_no_refund')];
        }

        $refundAmount = $amount ?? $payment->amount;
        $maxRefund = $payment->amount - $payment->refunded_amount;

        if ($refundAmount > $maxRefund) {
            return ['success' => false, 'message' => __('app.common.refund_amount_exceeds_balance')];
        }

        DB::beginTransaction();
        try {
            $payment->refunded_amount += $refundAmount;
            $payment->refunded_at = now();

            if ($payment->refunded_amount >= $payment->amount) {
                $payment->status = 'refunded';
            } else {
                $payment->status = 'partially_refunded';
            }

            $payment->save();
            DB::commit();

            $this->clearDashboardCache();

            Log::info('支付退款成功', [
                'payment_id' => $payment->id,
                'amount' => $refundAmount,
                'status' => $payment->status,
            ]);

            return ['success' => true, 'message' => __('app.common.refund_success'), 'data' => $payment->fresh()->toArray()];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('退款失败', ['payment_id' => $id, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => __('app.common.refund_processing_failed', ['message' => $e->getMessage()])];
        }
    }

    // ─── 统计分析 ────────────────────────────────

    /**
     * 支付趋势 (按天)
     */
    public function getTrend(int $days = 30): array
    {
        $start = now()->subDays($days)->startOfDay();

        $trends = Payment::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = \'completed\' THEN amount ELSE 0 END) as revenue'),
            DB::raw('COUNT(CASE WHEN status = \'completed\' THEN 1 END) as completed_count')
        )
            ->where('created_at', '>=', $start)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // 填充空白日期
        $result = [];
        $cursor = $start->copy();
        $trendMap = $trends->keyBy('date');
        while ($cursor <= now()) {
            $dateKey = $cursor->format('Y-m-d');
            $dayData = $trendMap->get($dateKey);
            $result[] = [
                'date' => $dateKey,
                'total' => $dayData ? (int) $dayData->total : 0,
                'revenue' => round($dayData ? (float) $dayData->revenue : 0, 2),
                'completed_count' => $dayData ? (int) $dayData->completed_count : 0,
            ];
            $cursor->addDay();
        }

        return $result;
    }
}
